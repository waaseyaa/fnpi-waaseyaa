<?php

declare(strict_types=1);

namespace App\CoIntelligence;

/**
 * Builds the grounded, cited system prompt and the user message for FNPI's
 * Co-Intelligence chat. Pure and deterministic so the prompt contract can be
 * tested.
 *
 * Mirrors oiatc's ChatPromptBuilder: the model answers ONLY from the supplied
 * passages, cites the source it used, and refuses cleanly when the passages do
 * not cover the question. This is FNPI's own internal, authenticated workspace,
 * so the corpus may include confidential material; the model still must not
 * invent facts, numbers, names, or contacts that are not in the passages.
 */
final class ChatPromptBuilder
{
    /** Standard refusal, also used directly when retrieval finds nothing. */
    public const NO_ANSWER = "I could not find that in FNPI's current knowledge base. Try rephrasing, or add the source document to the knowledge set.";

    public function system(): string
    {
        return <<<PROMPT
            You are Co-Intelligence, the internal AI assistant for First Nations Procurement Inc. (FNPI), running inside FNPI's own sovereign Anokii workspace. You answer questions for FNPI's own team using ONLY the numbered passages provided in the user's message. Those passages come from FNPI's own knowledge base: its narrative, master plan, tech-lane summary and business model, the procurement and vendor materials, the live public site copy, and the Identity Workspace pillars.

            Rules:
            - Answer ONLY from the passages. Do not use outside knowledge.
            - If the passages do not contain the answer, reply exactly: "{$this->noAnswer()}" Do not guess.
            - Cite the source at the end of each relevant point, as "(source: <title>)". Use only title values that appear in the passages.
            - This is an internal, confidential workspace, so you may discuss anything the passages contain. But never invent facts, numbers, names, emails, links, or contacts that are not in the passages.
            - Keep answers clear and plain. Use short paragraphs or bullet points.
            - Never use em dashes or en dashes. Use commas, periods, or parentheses instead.
            PROMPT;
    }

    /**
     * @param list<Passage> $passages
     */
    public function userMessage(string $question, array $passages): string
    {
        $blocks = [];
        foreach ($passages as $i => $p) {
            $n = $i + 1;
            $heading = $p->heading !== '' ? $p->heading : '(intro)';
            $blocks[] = "[Passage {$n}] title: {$p->title} | section: {$heading}\n{$p->text}";
        }
        $context = $blocks === [] ? '(no passages found)' : implode("\n\n", $blocks);

        return "Question: {$question}\n\nPassages:\n{$context}";
    }

    public function noAnswer(): string
    {
        return self::NO_ANSWER;
    }

    /**
     * Deterministically strip em dashes (U+2014) and en dashes (U+2013) from
     * model text before it ships, so a stray dash never reaches the browser even
     * if the model ignores the system-prompt rule. An em dash collapses with its
     * surrounding spaces into a comma; an en dash becomes a plain hyphen so a
     * numeric range stays readable. Newlines are left intact. Pure/testable.
     */
    public static function sanitizeDashes(string $text): string
    {
        $text = preg_replace('/[ \t]*\x{2014}[ \t]*/u', ', ', $text) ?? $text;

        return str_replace("\u{2013}", '-', $text);
    }
}
