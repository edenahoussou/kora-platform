# -*- coding: utf-8 -*-
"""Génère le manuel d'utilisation de l'app Passager : HTML puis PDF.

    python build_manual.py

Même chaîne que le guide de recette — Chrome pour l'impression, PyMuPDF
pour la pagination — mais un document différent : celui-ci s'adresse au
voyageur.

Les figures sont numérotées automatiquement, dans leur ordre
d'apparition, et la table des figures est construite à partir de cette
même numérotation : impossible qu'elle se désynchronise du corps du
texte.
"""

import io
import os
import shutil
import subprocess
import sys
from html import escape

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import manual_content as M  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
SCREENS = os.path.join(HERE, "screens")
HTML_OUT = os.path.join(HERE, "manuel.html")
PDF_OUT = os.path.join(HERE, "Manuel-KORA-EXPRESS-Passager.pdf")
INTER = os.path.join(HERE, "fonts", "Inter.ttf")

CHROME_CANDIDATES = [
    r"C:\Program Files\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    r"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
]

CSS = """
/* Inter en fichier variable : un seul fichier couvre toutes les graisses,
   et le document ne dépend d'aucun accès réseau au moment du rendu. */
@font-face {
  font-family: 'Inter';
  src: url('fonts/Inter.ttf') format('truetype-variations');
  font-weight: 100 900;
  font-style: normal;
  font-display: block;
}

:root {
  --kora: #001D4E;
  --gold: #F6B000;
  --digital: #0057B8;
  --mist: #F4F6F8;
  --graphite: #111827;
  --muted: #6B7280;
  --line: rgba(0, 29, 78, 0.12);
}

* { box-sizing: border-box; }

@page { size: A4; margin: 17mm 16mm 19mm 16mm; }

html, body {
  margin: 0;
  padding: 0;
  background: #fff;
  color: var(--graphite);
  font-family: 'Inter', "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  font-weight: 400;
  font-size: 10.4pt;
  line-height: 1.55;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

h1, h2, h3 { margin: 0; letter-spacing: -0.02em; }
p { margin: 0 0 8pt; }
strong { font-weight: 700; color: var(--kora); }
.cap b, table.figs td.n { font-weight: 700; }
em { font-style: italic; }

/* ---------------------------------------------------------- couverture */

.cover { break-after: page; height: 258mm; display: flex; flex-direction: column; }

.cover-panel {
  background: var(--kora);
  color: #fff;
  border-radius: 6mm;
  padding: 16mm 14mm;
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.cover-logo {
  background: #fff;
  border-radius: 2.5mm;
  padding: 3.5mm 5mm;
  display: inline-block;
}
.cover-logo img { height: 11mm; width: auto; display: block; }

.cover-rule {
  width: 26mm; height: 1.6mm; background: var(--gold);
  border-radius: 1mm; margin: 0 0 7mm;
}

.cover-eyebrow {
  color: var(--gold); font-size: 10pt; font-weight: 700;
  letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 5mm;
}

.cover h1 { font-size: 32pt; font-weight: 800; line-height: 1.04; margin-bottom: 6mm; }

.cover-lead {
  font-size: 11.5pt; line-height: 1.5;
  color: rgba(255, 255, 255, 0.82); max-width: 125mm;
}

.cover-meta {
  display: flex; gap: 12mm;
  border-top: 0.4mm solid rgba(246, 176, 0, 0.4); padding-top: 6mm;
}
.cover-meta div { font-size: 9pt; }

.cover-author {
  border-top: 0.4mm solid rgba(246, 176, 0, 0.4);
  padding-top: 6mm; margin-top: 7mm;
  display: flex; gap: 14mm;
}
.cover-author div { font-size: 10pt; font-weight: 700; }
.cover-author span {
  display: block; color: var(--gold); font-weight: 700;
  letter-spacing: 0.08em; text-transform: uppercase;
  font-size: 7.5pt; margin-bottom: 1.5mm;
}
.cover-meta span {
  display: block; color: var(--gold); font-weight: 700;
  letter-spacing: 0.08em; text-transform: uppercase;
  font-size: 7.5pt; margin-bottom: 1.5mm;
}

/* ------------------------------------------------------------- sections */

.section { break-before: page; }

.section-head { border-left: 1.4mm solid var(--gold); padding-left: 6mm; margin-bottom: 8mm; }

.eyebrow {
  color: var(--digital); font-size: 8pt; font-weight: 700;
  letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 2mm;
}

.section h2 { color: var(--kora); font-size: 20pt; font-weight: 800; line-height: 1.12; margin-bottom: 3mm; }

.section-intro { color: #374151; font-size: 10.6pt; margin: 0; }

.sub { color: var(--kora); font-size: 12pt; font-weight: 700; margin: 8mm 0 4mm; }

/* -------------------------------------------------------------- listes */

ol.toc { margin: 0; padding: 0; list-style: none; counter-reset: toc; }
ol.toc li {
  counter-increment: toc; display: flex; align-items: baseline; gap: 4mm;
  padding: 2.8mm 0; border-bottom: 0.3mm solid var(--line); font-size: 10.8pt;
}
ol.toc li::before {
  content: counter(toc, decimal-leading-zero);
  color: var(--gold); font-weight: 800; font-size: 9pt; min-width: 7mm;
}

ul.plain { margin: 0 0 6pt; padding-left: 5mm; }
ul.plain li { margin-bottom: 2.5mm; }

table.figs { width: 100%; border-collapse: collapse; font-size: 9.6pt; }
table.figs td { padding: 1.9mm 0; border-bottom: 0.3mm solid var(--line); vertical-align: baseline; }
table.figs td.n { color: var(--gold); font-weight: 800; width: 22mm; white-space: nowrap; }
table.figs td.p { text-align: right; color: var(--muted); width: 14mm; }

/* ---------------------------------------------------------------- pas */

.step { display: flex; gap: 4.5mm; margin-bottom: 5mm; break-inside: avoid; }
.step-num {
  flex: 0 0 7mm; height: 7mm; border-radius: 50%;
  background: var(--kora); color: #fff; font-size: 9pt; font-weight: 800;
  display: flex; align-items: center; justify-content: center; margin-top: 0.6mm;
}
.step-body { flex: 1; }
.step-title { font-weight: 700; color: var(--kora); font-size: 10.9pt; margin-bottom: 1.2mm; }

/* ------------------------------------------------------------ encadrés */

.box { border-radius: 3mm; padding: 4mm 5mm; margin: 4mm 0 5mm; font-size: 10pt; break-inside: avoid; }
.box-title {
  font-size: 8pt; font-weight: 700; letter-spacing: 0.1em;
  text-transform: uppercase; margin-bottom: 1.8mm;
}
.box p:last-child { margin-bottom: 0; }

.note { background: var(--mist); border-left: 1.2mm solid var(--digital); }
.note .box-title { color: var(--digital); }

.warn { background: rgba(246, 176, 0, 0.12); border-left: 1.2mm solid var(--gold); }
.warn .box-title { color: #8A6200; }

/* ------------------------------------------------------------- figures */

.figrow { display: flex; gap: 7mm; justify-content: center; margin: 5mm 0 6mm; break-inside: avoid; }
.fig { width: 60mm; }
.fig.solo { width: 55mm; margin: 0 auto; }

.frame { border: 0.5mm solid var(--kora); border-radius: 4mm; overflow: hidden; background: #000; }
.frame img { width: 100%; display: block; }

.frame-todo {
  border: 0.4mm dashed rgba(0, 29, 78, 0.5); border-radius: 4mm;
  background: var(--mist); aspect-ratio: 9 / 19.5; padding: 6mm 5mm;
  display: flex; align-items: center; justify-content: center; text-align: center;
  color: var(--kora); font-size: 8.6pt;
}

.cap { margin-top: 2.2mm; font-size: 8.4pt; text-align: center; color: var(--muted); }
.cap b { color: var(--kora); font-weight: 800; }

/* ----------------------------------------------------------------- faq */

.qa { margin-bottom: 5mm; break-inside: avoid; }
.qa .q { font-weight: 700; color: var(--kora); font-size: 10.6pt; margin-bottom: 1.2mm; }
.qa .a { margin: 0; }

/* -------------------------------------------------------------- tables */

table.info { width: 100%; border-collapse: collapse; font-size: 9.8pt; }
table.info td { padding: 2.6mm 3mm; border-bottom: 0.3mm solid var(--line); vertical-align: top; }
table.info td:first-child { font-weight: 700; color: var(--kora); width: 42%; }

/* ------------------------------------------------- descriptions d'écran */

.look {
  background: #fff;
  border: 0.35mm solid var(--line);
  border-radius: 3mm;
  padding: 4.5mm 5mm 2mm;
  margin: 4mm 0 6mm;
  break-inside: avoid;
}

.look-title {
  color: var(--kora); font-size: 8pt; font-weight: 700;
  letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 3mm;
}

dl { margin: 0; }
dt {
  color: var(--kora); font-weight: 700; font-size: 10.1pt;
  margin-bottom: 0.8mm;
}
dd { margin: 0 0 3.2mm; color: #374151; }
dd:last-child { margin-bottom: 2mm; }

dl.terms dt { font-size: 10.4pt; }
dl.terms dd { margin-bottom: 4mm; }
"""


def chrome():
    for path in CHROME_CANDIDATES:
        if os.path.exists(path):
            return path
    found = shutil.which("chrome") or shutil.which("msedge")
    if found:
        return found
    raise SystemExit("Aucun Chrome/Edge trouvé pour l'impression PDF.")


class Figures:
    """Numérote les figures et retient de quoi bâtir leur table."""

    def __init__(self):
        self.entries = []  # (numéro, légende)

    def html(self, slot, solo=False):
        caption = M.FIGURES.get(slot, slot)
        number = len(self.entries) + 1
        self.entries.append((number, caption))

        png = os.path.join(SCREENS, slot + ".png")
        if os.path.exists(png):
            inner = '<div class="frame"><img src="screens/%s.png" alt="%s"></div>' % (
                slot,
                escape(caption),
            )
        else:
            inner = '<div class="frame-todo">Figure à venir</div>'

        return (
            '<div class="fig%s">%s<div class="cap"><b>Figure %d</b> — %s</div></div>'
            % (" solo" if solo else "", inner, number, escape(caption))
        )


def render_blocks(blocks, figures):
    out = []
    step_no = 0

    for block in blocks:
        kind = block[0]

        if kind == "para":
            out.append("<p>%s</p>" % block[1])

        elif kind == "step":
            step_no += 1
            out.append(
                '<div class="step"><div class="step-num">%d</div>'
                '<div class="step-body"><div class="step-title">%s</div>'
                "<p>%s</p></div></div>" % (step_no, escape(block[1]), block[2])
            )

        elif kind == "note":
            out.append(
                '<div class="box note"><div class="box-title">Bon à savoir</div>'
                "<p>%s</p></div>" % block[1]
            )

        elif kind == "warn":
            out.append(
                '<div class="box warn"><div class="box-title">À retenir</div>'
                "<p>%s</p></div>" % block[1]
            )

        elif kind == "fig":
            out.append('<div class="figrow">%s</div>' % figures.html(block[1], solo=True))

        elif kind == "figs":
            out.append(
                '<div class="figrow">%s</div>'
                % "".join(figures.html(slot) for slot in block[1])
            )

        elif kind == "sub":
            out.append('<div class="sub">%s</div>' % escape(block[1]))

        elif kind == "defs":
            rows = "".join(
                "<dt>%s</dt><dd>%s</dd>" % (escape(term), definition)
                for term, definition in block[1]
            )
            out.append('<dl class="terms">%s</dl>' % rows)

        elif kind == "look":
            rows = "".join(
                "<dt>%s</dt><dd>%s</dd>" % (escape(label), description)
                for label, description in block[2]
            )
            out.append(
                '<div class="look"><div class="look-title">%s</div>'
                "<dl>%s</dl></div>" % (escape(block[1]), rows)
            )

        elif kind == "faq":
            for question, answer in block[1]:
                out.append(
                    '<div class="qa"><div class="q">%s</div><p class="a">%s</p></div>'
                    % (escape(question), escape(answer))
                )

        else:
            raise ValueError("bloc inconnu : %r" % kind)

    return "\n".join(out)


def render():
    figures = Figures()

    # Les chapitres d'abord : c'est leur rendu qui numérote les figures.
    body = []
    for index, chapter in enumerate(M.CHAPTERS, start=1):
        body.append('<section class="section">')
        body.append('<div class="section-head">')
        body.append('<div class="eyebrow">Chapitre %d</div>' % index)
        body.append("<h2>%s</h2>" % escape(chapter["title"]))
        body.append('<p class="section-intro">%s</p></div>' % chapter["intro"])
        body.append(render_blocks(chapter["blocks"], figures))
        body.append("</section>")

    # Contact
    body.append('<section class="section">')
    body.append('<div class="section-head"><div class="eyebrow">Pour finir</div>')
    body.append("<h2>%s</h2>" % escape(M.CONTACT["title"]))
    body.append('<p class="section-intro">%s</p></div>' % M.CONTACT["lead"])
    body.append('<table class="info"><tbody>')
    for label, value in M.CONTACT["rows"]:
        body.append("<tr><td>%s</td><td>%s</td></tr>" % (escape(label), escape(value)))
    body.append("</tbody></table></section>")

    parts = []
    add = parts.append

    add("<!doctype html><html lang='fr'><head><meta charset='utf-8'>")
    add("<title>Manuel d'utilisation — KORA EXPRESS Passager</title>")
    add("<style>%s</style></head><body>" % CSS)

    # --- couverture
    add('<section class="cover"><div class="cover-panel"><div>')
    add('<div class="cover-logo"><img src="kora_logo_mark.png" alt="KORA EXPRESS"></div>')
    add("</div><div>")
    add('<div class="cover-rule"></div>')
    add('<div class="cover-eyebrow">Application Passager · Bus</div>')
    add("<h1>Manuel<br>d'utilisation</h1>")
    add('<p class="cover-lead">%s</p>' % M.INTRO["lead"])
    add('<div class="cover-author">')
    add("<div><span>Auteur</span>%s</div>" % escape(M.AUTHOR))
    add("<div><span>Rédigé le</span>%s</div>" % escape(M.WRITTEN_ON))
    add("</div>")
    add("</div>")
    add('<div class="cover-meta">')
    add("<div><span>Version</span>%s</div>" % M.APP_VERSION)
    add("<div><span>Plateforme</span>Android</div>")
    add("<div><span>Chapitres</span>%d</div>" % len(M.CHAPTERS))
    add("<div><span>Figures</span>%d</div>" % len(figures.entries))
    add("</div></div></section>")

    # --- bienvenue
    add('<section class="section">')
    add('<div class="section-head"><div class="eyebrow">Avant de commencer</div>')
    add("<h2>Qu'est-ce que KORA EXPRESS ?</h2>")
    add('<p class="section-intro">%s</p></div>' % M.INTRO["lead"])

    add('<div class="sub">%s</div>' % escape(M.INTRO["what_title"]))
    add(
        '<dl class="terms">%s</dl>'
        % "".join(
            "<dt>%s</dt><dd>%s</dd>" % (escape(t), d) for t, d in M.INTRO["what"]
        )
    )

    add('<div class="sub">%s</div>' % escape(M.INTRO["who_title"]))
    for paragraph in M.INTRO["who"]:
        add("<p>%s</p>" % paragraph)

    add('<div class="sub">%s</div>' % escape(M.INTRO["needs_title"]))
    add('<ul class="plain">%s</ul>' % "".join("<li>%s</li>" % n for n in M.INTRO["needs"]))

    add('<div class="sub">%s</div>' % escape(M.INTRO["howto_title"]))
    add('<ul class="plain">%s</ul>' % "".join("<li>%s</li>" % n for n in M.INTRO["howto"]))
    add("</section>")

    # --- sommaire
    add('<section class="section">')
    add('<div class="section-head"><div class="eyebrow">Repères</div>')
    add("<h2>Sommaire</h2></div>")
    add("<ol class='toc'>")
    for chapter in M.CHAPTERS:
        add("<li>%s</li>" % escape(chapter["title"]))
    add("<li>%s</li>" % escape(M.CONTACT["title"]))
    add("</ol></section>")

    # --- table des figures
    add('<section class="section">')
    add('<div class="section-head"><div class="eyebrow">Repères</div>')
    add("<h2>Table des figures</h2>")
    add(
        '<p class="section-intro">Chaque écran décrit dans ce manuel porte '
        "un numéro. Retrouvez ici celui que vous avez sous les yeux.</p></div>"
    )
    add('<table class="figs"><tbody>')
    for number, caption in figures.entries:
        add(
            '<tr><td class="n">Figure %d</td><td>%s</td></tr>'
            % (number, escape(caption))
        )
    add("</tbody></table></section>")

    add("\n".join(body))
    add("</body></html>")

    html = "\n".join(parts)
    io.open(HTML_OUT, "w", encoding="utf-8", newline="\n").write(html)
    return figures


def to_pdf():
    subprocess.run(
        [
            chrome(),
            "--headless=new",
            "--disable-gpu",
            "--no-sandbox",
            "--no-pdf-header-footer",
            "--print-to-pdf-no-header",
            "--virtual-time-budget=10000",
            "--print-to-pdf=" + PDF_OUT,
            "file:///" + HTML_OUT.replace("\\", "/"),
        ],
        check=True,
        capture_output=True,
        timeout=180,
    )


def stamp():
    import pymupdf

    doc = pymupdf.open(PDF_OUT)
    total = doc.page_count
    navy = (0.0, 29 / 255.0, 78 / 255.0)
    muted = (0.42, 0.45, 0.50)
    left = "KORA EXPRESS · Manuel d'utilisation · Application Passager"

    for number, page in enumerate(doc, start=1):
        if number == 1:
            continue
        width, height = page.rect.width, page.rect.height
        y = height - 30
        page.draw_line(
            pymupdf.Point(45, y - 12),
            pymupdf.Point(width - 45, y - 12),
            color=(0.90, 0.92, 0.94),
            width=0.5,
        )
        # Le pied de page aussi en Inter : une seule police dans tout le
        # document, jusqu'au numéro de page.
        page.insert_text(
            pymupdf.Point(45, y),
            left,
            fontsize=7,
            color=muted,
            fontname="inter",
            fontfile=INTER,
        )
        label = "%d / %d" % (number, total)
        page.insert_text(
            pymupdf.Point(
                width - 45 - pymupdf.get_text_length(label, fontsize=7.5, fontname="helv"),
                y,
            ),
            label,
            fontsize=7.5,
            color=navy,
            fontname="inter",
            fontfile=INTER,
        )

    doc.saveIncr()
    doc.close()
    return total


if __name__ == "__main__":
    # Les chapitres sont rendus avant la couverture : le nombre de
    # figures est donc déjà connu quand la couverture l'affiche.
    figures = render()
    to_pdf()
    pages = stamp()

    missing = [
        slot
        for slot in M.FIGURES
        if not os.path.exists(os.path.join(SCREENS, slot + ".png"))
    ]
    print("PDF      : %s (%d pages)" % (os.path.basename(PDF_OUT), pages))
    print("Figures  : %d numérotées" % len(figures.entries))
    if missing:
        print("Sans image : " + ", ".join(sorted(missing)))
