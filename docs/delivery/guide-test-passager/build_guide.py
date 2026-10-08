# -*- coding: utf-8 -*-
"""Génère le guide de test de l'app Passager : HTML puis PDF.

    python build_guide.py

Rend `guide.html`, l'imprime en PDF avec Chrome headless (seul moteur
disponible ici qui honore vraiment la CSS d'impression), puis tamponne le
pied de page et la pagination avec PyMuPDF — Chrome ne sait pas numéroter
les pages sans y coller aussi l'URL du fichier.

Les captures vivent dans `screens/<slot>.png`. Celles qui manquent sont
rendues en cadre pointillé portant la consigne de prise de vue : le
document reste utilisable, et ce qui reste à faire se voit.
"""

import io
import os
import shutil
import subprocess
import sys
from html import escape

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import content as C  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
SCREENS = os.path.join(HERE, "screens")
HTML_OUT = os.path.join(HERE, "guide.html")
PDF_OUT = os.path.join(HERE, "Guide-Test-KORA-EXPRESS-Passager.pdf")

CHROME_CANDIDATES = [
    r"C:\Program Files\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    r"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
]

# Palette officielle, reprise de packages/flutter-design-system
# (`AppColors`) : une seule source de vérité pour la marque.
CSS = """
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

@page {
  size: A4;
  margin: 17mm 16mm 19mm 16mm;
}

html, body {
  margin: 0;
  padding: 0;
  background: #fff;
  color: var(--graphite);
  font-family: Aptos, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  font-size: 10.2pt;
  line-height: 1.5;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

h1, h2, h3, h4 { margin: 0; letter-spacing: -0.02em; }
p { margin: 0 0 7pt; }
strong { font-weight: 700; color: var(--kora); }
em { font-style: italic; }

/* ---------------------------------------------------------- couverture */

.cover {
  break-after: page;
  height: 258mm;
  display: flex;
  flex-direction: column;
}

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
  width: 26mm;
  height: 1.6mm;
  background: var(--gold);
  border-radius: 1mm;
  margin: 0 0 7mm;
}

.cover-eyebrow {
  color: var(--gold);
  font-size: 10pt;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin-bottom: 5mm;
}

.cover h1 {
  font-size: 30pt;
  font-weight: 800;
  line-height: 1.05;
  margin-bottom: 6mm;
}

.cover-lead {
  font-size: 11.5pt;
  line-height: 1.5;
  color: rgba(255, 255, 255, 0.82);
  max-width: 125mm;
}

.cover-meta {
  display: flex;
  gap: 12mm;
  border-top: 0.4mm solid rgba(246, 176, 0, 0.4);
  padding-top: 6mm;
}

.cover-meta div { font-size: 9pt; }

.cover-meta span {
  display: block;
  color: var(--gold);
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-size: 7.5pt;
  margin-bottom: 1.5mm;
}

/* ------------------------------------------------------------ sommaire */

.toc { break-after: page; }

.toc ol { margin: 0; padding: 0; list-style: none; counter-reset: toc; }

.toc ol li {
  counter-increment: toc;
  display: flex;
  align-items: baseline;
  gap: 4mm;
  padding: 2.6mm 0;
  border-bottom: 0.3mm solid var(--line);
  font-size: 10.5pt;
}

.toc ol li::before {
  content: counter(toc, decimal-leading-zero);
  color: var(--gold);
  font-weight: 800;
  font-size: 9pt;
  min-width: 7mm;
}

.toc .toc-eyebrow {
  margin-left: auto;
  color: var(--muted);
  font-size: 8.5pt;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

/* ------------------------------------------------------------ chapitre */

.chapter { break-before: page; }

.chapter-head {
  border-left: 1.4mm solid var(--gold);
  padding-left: 6mm;
  margin-bottom: 7mm;
}

.eyebrow {
  color: var(--digital);
  font-size: 8pt;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin-bottom: 2mm;
}

.chapter h2 {
  color: var(--kora);
  font-size: 19pt;
  font-weight: 800;
  line-height: 1.1;
  margin-bottom: 3mm;
}

.chapter-intro { color: #374151; font-size: 10.4pt; margin: 0; }

.section-label {
  color: var(--kora);
  font-size: 12pt;
  font-weight: 800;
  margin: 8mm 0 4mm;
}

/* ---------------------------------------------------------------- pas */

.step {
  display: flex;
  gap: 4.5mm;
  margin-bottom: 5mm;
  break-inside: avoid;
}

.step-num {
  flex: 0 0 7mm;
  height: 7mm;
  border-radius: 50%;
  background: var(--kora);
  color: #fff;
  font-size: 9pt;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 0.6mm;
}

.step-body { flex: 1; }

.step-title {
  font-weight: 800;
  color: var(--kora);
  font-size: 10.8pt;
  margin-bottom: 1.2mm;
}

/* ------------------------------------------------------------ encadrés */

.box {
  border-radius: 3mm;
  padding: 4mm 5mm;
  margin: 4mm 0 5mm;
  font-size: 10pt;
  break-inside: avoid;
}

.box-title {
  font-size: 8pt;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  margin-bottom: 1.8mm;
}

.expect {
  background: rgba(246, 176, 0, 0.1);
  border-left: 1.2mm solid var(--gold);
}
.expect .box-title { color: #8A6200; }

.check {
  background: #fff;
  border: 0.35mm dashed rgba(0, 29, 78, 0.45);
}
.check .box-title { color: var(--kora); }

.note {
  background: var(--mist);
  border-left: 1.2mm solid var(--digital);
}
.note .box-title { color: var(--digital); }

.box p:last-child { margin-bottom: 0; }

/* ------------------------------------------------------------ captures */

.shots {
  display: flex;
  gap: 7mm;
  justify-content: center;
  margin: 5mm 0 6mm;
  break-inside: avoid;
}

.shot { width: 60mm; }
.shot.solo { width: 66mm; margin: 0 auto; }

.frame {
  border: 0.5mm solid var(--kora);
  border-radius: 4mm;
  overflow: hidden;
  background: #000;
}

.frame img { width: 100%; display: block; }

.frame-todo {
  border: 0.4mm dashed rgba(0, 29, 78, 0.5);
  border-radius: 4mm;
  background: var(--mist);
  aspect-ratio: 9 / 19.5;
  padding: 6mm 5mm;
  display: flex;
  flex-direction: column;
  justify-content: center;
  text-align: center;
}

.frame-todo .slot {
  color: var(--gold);
  font-weight: 800;
  font-size: 8pt;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: 3mm;
}

.frame-todo .what {
  color: var(--kora);
  font-size: 8.6pt;
  line-height: 1.45;
}

.caption {
  margin-top: 2mm;
  font-size: 8.4pt;
  color: var(--muted);
  text-align: center;
}

.caption b { color: var(--kora); font-weight: 700; }

/* -------------------------------------------------------------- tables */

table { width: 100%; border-collapse: collapse; font-size: 9.6pt; }

th {
  background: var(--kora);
  color: #fff;
  text-align: left;
  font-size: 8pt;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 2.6mm 3mm;
}

td {
  padding: 2.4mm 3mm;
  border-bottom: 0.3mm solid var(--line);
  vertical-align: top;
}

tr:nth-child(even) td { background: #FAFBFC; }

.tick {
  width: 5mm;
  height: 5mm;
  border: 0.35mm solid var(--kora);
  border-radius: 1mm;
  display: block;
}

.testdata td:first-child { font-weight: 700; color: var(--kora); width: 55%; }
.testdata td:last-child { font-variant-numeric: tabular-nums; }

ul.plain { margin: 0 0 6pt; padding-left: 5mm; }
ul.plain li { margin-bottom: 2mm; }
"""


def chrome():
    for path in CHROME_CANDIDATES:
        if os.path.exists(path):
            return path
    found = shutil.which("chrome") or shutil.which("msedge")
    if found:
        return found
    raise SystemExit("Aucun Chrome/Edge trouvé pour l'impression PDF.")


def shot_html(slot, caption, solo=False):
    """Un cadre de capture, ou sa consigne de prise de vue."""
    png = os.path.join(SCREENS, slot + ".png")
    jpg = os.path.join(SCREENS, slot + ".jpg")
    src = png if os.path.exists(png) else (jpg if os.path.exists(jpg) else None)

    if src:
        rel = "screens/" + os.path.basename(src)
        inner = '<div class="frame"><img src="%s" alt="%s"></div>' % (
            rel,
            escape(caption),
        )
    else:
        inner = (
            '<div class="frame-todo"><div class="slot">%s</div>'
            '<div class="what">%s</div></div>'
        ) % (escape(slot), escape(C.SHOTS.get(slot, "")))

    return '<div class="shot%s">%s<div class="caption"><b>%s</b></div></div>' % (
        " solo" if solo else "",
        inner,
        escape(caption),
    )


def render_blocks(blocks):
    out = []
    step_no = 0

    for block in blocks:
        kind = block[0]

        if kind == "step":
            step_no += 1
            out.append(
                '<div class="step"><div class="step-num">%d</div>'
                '<div class="step-body"><div class="step-title">%s</div>'
                "<p>%s</p></div></div>" % (step_no, escape(block[1]), block[2])
            )

        elif kind == "expect":
            out.append(
                '<div class="box expect"><div class="box-title">'
                "Résultat attendu</div><p>%s</p></div>" % block[1]
            )

        elif kind == "check":
            out.append(
                '<div class="box check"><div class="box-title">'
                "Cas à provoquer</div><p>%s</p></div>" % block[1]
            )

        elif kind == "note":
            out.append(
                '<div class="box note"><div class="box-title">'
                "Bon à savoir</div><p>%s</p></div>" % block[1]
            )

        elif kind == "shot":
            out.append('<div class="shots">%s</div>' % shot_html(block[1], block[2], True))

        elif kind == "shots":
            out.append(
                '<div class="shots">%s</div>'
                % "".join(shot_html(slot, cap) for slot, cap in block[1])
            )

        else:
            raise ValueError("bloc inconnu : %r" % kind)

    return "\n".join(out)


def render():
    parts = []
    add = parts.append

    add("<!doctype html><html lang='fr'><head><meta charset='utf-8'>")
    add("<title>Guide de test — KORA EXPRESS Passager</title>")
    add("<style>%s</style></head><body>" % CSS)

    # --- couverture
    add('<section class="cover"><div class="cover-panel"><div>')
    add('<div class="cover-logo">'
        '<img src="kora_logo_mark.png" alt="KORA EXPRESS"></div>')
    add("</div><div>")
    add('<div class="cover-rule"></div>')
    add('<div class="cover-eyebrow">Application Passager · Bus</div>')
    add("<h1>Guide de test<br>de l'application<br>Passager</h1>")
    add(
        '<p class="cover-lead">De la création du compte au billet présenté '
        "hors ligne : chaque étape du parcours voyageur, ce qu'il faut "
        "vérifier, et les pannes à provoquer pour être sûr que "
        "l'application s'en relève.</p>"
    )
    add("</div>")
    add('<div class="cover-meta">')
    add("<div><span>Version testée</span>%s</div>" % C.APP_VERSION)
    add("<div><span>Plateforme</span>Android</div>")
    add("<div><span>Chapitres</span>%d</div>" % len(C.CHAPTERS))
    add("<div><span>Captures</span>%d écrans</div>" % len(C.SHOTS))
    add("</div></div></section>")

    # --- sommaire + à propos
    add('<section class="toc">')
    add('<div class="chapter-head"><div class="eyebrow">Repères</div>')
    add("<h2>Sommaire</h2></div>")
    add("<ol>")
    for ch in C.CHAPTERS:
        add(
            '<li>%s<span class="toc-eyebrow">%s</span></li>'
            % (escape(ch["title"]), escape(ch["eyebrow"]))
        )
    add("</ol></section>")

    add('<section class="chapter">')
    add('<div class="chapter-head"><div class="eyebrow">Avant de commencer</div>')
    add("<h2>%s</h2>" % escape(C.INTRO["title"]))
    add('<p class="chapter-intro">%s</p></div>' % C.INTRO["lead"])
    for title, items in C.INTRO["blocks"]:
        add('<div class="step-title" style="margin-top:5mm">%s</div>' % escape(title))
        add('<ul class="plain">%s</ul>' % "".join("<li>%s</li>" % i for i in items))

    add('<div class="section-label">Données de test</div>')
    add('<table class="testdata"><tbody>')
    for label, value in C.INTRO["testdata"]:
        add("<tr><td>%s</td><td>%s</td></tr>" % (escape(label), escape(value)))
    add("</tbody></table>")
    add("</section>")

    # --- chapitres
    for index, ch in enumerate(C.CHAPTERS, start=1):
        add('<section class="chapter">')
        add('<div class="chapter-head">')
        add('<div class="eyebrow">%02d · %s</div>' % (index, escape(ch["eyebrow"])))
        add("<h2>%s</h2>" % escape(ch["title"]))
        add('<p class="chapter-intro">%s</p></div>' % ch["intro"])
        add(render_blocks(ch["blocks"]))
        add("</section>")

    # --- annexe : checklist
    add('<section class="chapter">')
    add('<div class="chapter-head"><div class="eyebrow">Annexe A</div>')
    add("<h2>Fiche de passage</h2>")
    add(
        '<p class="chapter-intro">À imprimer et cocher au fil des tests. '
        "Une ligne non cochée n'est pas un échec : c'est un test qui reste "
        "à faire, et la distinction compte dans un rapport.</p></div>"
    )
    add("<table><thead><tr><th>Ok</th><th>Partie</th><th>Vérification</th>")
    add("</tr></thead><tbody>")
    for area, item in C.CHECKLIST:
        add(
            '<tr><td><span class="tick"></span></td><td>%s</td><td>%s</td></tr>'
            % (escape(area), escape(item))
        )
    add("</tbody></table></section>")

    # --- annexe : rapport
    add('<section class="chapter">')
    add('<div class="chapter-head"><div class="eyebrow">Annexe B</div>')
    add("<h2>Signaler un défaut</h2>")
    add(
        '<p class="chapter-intro">Un défaut reproductible est corrigé vite ; '
        "un défaut raconté de mémoire revient trois fois. Ces huit lignes "
        "suffisent, et la capture d'écran vaut tout le reste.</p></div>"
    )
    add("<table><thead><tr><th>Ligne</th><th>Ce qu'on attend</th>")
    add("</tr></thead><tbody>")
    for field, hint in C.REPORT_FIELDS:
        add("<tr><td><strong>%s</strong></td><td>%s</td></tr>" % (escape(field), escape(hint)))
    add("</tbody></table>")
    add(
        '<div class="box expect"><div class="box-title">Avant d\'envoyer</div>'
        "<p>Rejouez le défaut une seconde fois, depuis l'accueil. S'il ne "
        "revient pas, dites-le : un défaut intermittent se cherche "
        "autrement qu'un défaut systématique, et le savoir fait gagner une "
        "journée.</p></div>"
    )
    add("</section>")

    add("</body></html>")

    html = "\n".join(parts)
    io.open(HTML_OUT, "w", encoding="utf-8", newline="\n").write(html)
    return html


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
    """Pied de page et pagination, que Chrome ne sait pas poser seul."""
    import fitz

    doc = fitz.open(PDF_OUT)
    total = doc.page_count
    navy = (0.0, 29 / 255.0, 78 / 255.0)
    muted = (0.42, 0.45, 0.50)

    left = "KORA EXPRESS · Guide de test · Application Passager %s" % C.APP_VERSION

    for number, page in enumerate(doc, start=1):
        if number == 1:  # la couverture se passe de pied de page
            continue
        width, height = page.rect.width, page.rect.height
        y = height - 30

        page.draw_line(
            fitz.Point(45, y - 12),
            fitz.Point(width - 45, y - 12),
            color=(0.90, 0.92, 0.94),
            width=0.5,
        )
        page.insert_text(fitz.Point(45, y), left, fontsize=7, color=muted,
                         fontname="helv")
        label = "%d / %d" % (number, total)
        page.insert_text(
            fitz.Point(width - 45 - fitz.get_text_length(label, fontsize=7.5,
                                                         fontname="hebo"), y),
            label,
            fontsize=7.5,
            color=navy,
            fontname="hebo",
        )

    doc.saveIncr()
    doc.close()
    return total


if __name__ == "__main__":
    render()
    to_pdf()
    pages = stamp()

    missing = [
        slot
        for slot in C.SHOTS
        if not any(
            os.path.exists(os.path.join(SCREENS, slot + ext))
            for ext in (".png", ".jpg")
        )
    ]
    have = len(C.SHOTS) - len(missing)

    print("PDF   : %s (%d pages)" % (os.path.basename(PDF_OUT), pages))
    print("Captures : %d / %d en place" % (have, len(C.SHOTS)))
    if missing:
        print("Manquantes : " + ", ".join(sorted(missing)))
