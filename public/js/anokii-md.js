/*
 * Anokii chat markdown renderer (shared by Co-Intelligence + Identity chats).
 *
 * The agent streams Markdown; this turns it into clean HTML for a chat bubble.
 * Safety: the whole input is HTML-escaped FIRST, then markdown is applied to the
 * escaped text, so no model output can inject markup. Links are limited to
 * http(s) or root-relative. Output is wrapped in <div class="amd"> which the
 * shell styles (see _shell.html.twig).
 *
 * Supports: headings, horizontal rules, ordered/unordered lists, tables (with
 * pillar-status cells rendered as colored pills), bold, italic, inline code,
 * links, and blank-line paragraphs.
 *
 * window.AnokiiMd.render(text) -> html string.
 */
(function () {
  'use strict';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  // Inline spans, applied to ALREADY-ESCAPED text.
  function inline(s) {
    s = s.replace(/`([^`]+)`/g, function (m, c) { return '<code>' + c + '</code>'; });
    s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, t, u) {
      if (!/^https?:\/\//.test(u) && u.charAt(0) !== '/') { return t; }
      return '<a href="' + u + '" target="_blank" rel="noopener">' + t + '</a>';
    });
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');
    s = s.replace(/(^|[^_\w])_([^_\n]+)_(?![_\w])/g, '$1<em>$2</em>');
    return s;
  }

  // Known Identity pillar statuses render as colored pills (match the rail).
  var STATUS = { defined: 1, draft: 1, work: 1, gap: 1 };
  function tableCell(text) {
    var key = text.trim().toLowerCase();
    if (Object.prototype.hasOwnProperty.call(STATUS, key)) {
      return '<span class="stp stp-' + key + '">' + inline(text.trim()) + '</span>';
    }
    return inline(text.trim());
  }

  function isTableSep(line) {
    return /^\s*\|?\s*:?-{1,}:?\s*(\|\s*:?-{1,}:?\s*)+\|?\s*$/.test(line);
  }
  function splitRow(line) {
    return line.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map(function (c) { return c.trim(); });
  }
  function headingTag(level) {
    return level <= 2 ? 'h4' : (level === 3 ? 'h5' : 'h6');
  }

  function render(text) {
    var src = esc(text == null ? '' : String(text));
    var lines = src.split(/\n/);
    var out = [], i = 0, para = [], list = null;

    function flushP() { if (para.length) { out.push('<p>' + inline(para.join(' ')) + '</p>'); para = []; } }
    function flushL() {
      if (list) {
        out.push('<' + list.tag + '>' + list.items.map(function (it) { return '<li>' + inline(it) + '</li>'; }).join('') + '</' + list.tag + '>');
        list = null;
      }
    }
    function flush() { flushL(); flushP(); }

    while (i < lines.length) {
      var t = lines[i].trim();

      if (t === '') { flush(); i++; continue; }

      if (/^(\*\s*){3,}$/.test(t) || /^(-\s*){3,}$/.test(t) || /^(_\s*){3,}$/.test(t)) {
        flush(); out.push('<hr>'); i++; continue;
      }

      var hm = /^(#{1,6})\s+(.*)$/.exec(t);
      if (hm) { flush(); var tag = headingTag(hm[1].length); out.push('<' + tag + ' class="amh">' + inline(hm[2].trim()) + '</' + tag + '>'); i++; continue; }

      // Table: a pipe row followed by a |---|---| separator row.
      if (t.indexOf('|') > -1 && i + 1 < lines.length && isTableSep(lines[i + 1])) {
        flush();
        var head = splitRow(lines[i]);
        i += 2;
        var rows = [];
        while (i < lines.length && lines[i].trim() !== '' && lines[i].indexOf('|') > -1) { rows.push(splitRow(lines[i])); i++; }
        var thead = '<thead><tr>' + head.map(function (c) { return '<th>' + inline(c) + '</th>'; }).join('') + '</tr></thead>';
        var tbody = '<tbody>' + rows.map(function (r) {
          return '<tr>' + r.map(function (c) { return '<td>' + tableCell(c) + '</td>'; }).join('') + '</tr>';
        }).join('') + '</tbody>';
        out.push('<table class="amt">' + thead + tbody + '</table>');
        continue;
      }

      var um = /^[-*+]\s+(.*)$/.exec(t);
      if (um) { flushP(); if (!list || list.tag !== 'ul') { flushL(); list = { tag: 'ul', items: [] }; } list.items.push(um[1]); i++; continue; }

      var om = /^\d+[.)]\s+(.*)$/.exec(t);
      if (om) { flushP(); if (!list || list.tag !== 'ol') { flushL(); list = { tag: 'ol', items: [] }; } list.items.push(om[1]); i++; continue; }

      flushL(); para.push(t); i++;
    }
    flush();

    return '<div class="amd">' + (out.join('') || '<p>' + inline(src) + '</p>') + '</div>';
  }

  window.AnokiiMd = { render: render, esc: esc };
})();
