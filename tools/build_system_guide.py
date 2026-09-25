"""Build the editable Word guide from docs/SYSTEM_GUIDE.md using standard Python."""
from pathlib import Path
import re
from xml.sax.saxutils import escape
from zipfile import ZipFile, ZIP_DEFLATED
from xml.etree import ElementTree

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'docs' / 'SYSTEM_GUIDE.md'
OUTPUT = ROOT / 'docs' / 'Brooks_Agro_ERP_System_Guide.docx'
NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'


def runs(text):
    result = []
    for part in re.split(r'(\*\*.*?\*\*|`[^`]+`)', text):
        props = ''
        if part.startswith('**') and part.endswith('**'):
            props, part = '<w:b/>', part[2:-2]
        elif part.startswith('`') and part.endswith('`'):
            props, part = '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="19"/>', part[1:-1]
        result.append(f'<w:r><w:rPr>{props}</w:rPr><w:t xml:space="preserve">{escape(part)}</w:t></w:r>')
    return ''.join(result)


def paragraph(text, style='Normal'):
    return f'<w:p><w:pPr><w:pStyle w:val="{style}"/></w:pPr>{runs(text)}</w:p>'


def table(lines):
    rows = [[cell.strip() for cell in line.strip().strip('|').split('|')] for line in lines]
    rows = [row for row in rows if not all(re.fullmatch(r':?-+:?', cell) for cell in row)]
    width = 9600 // len(rows[0])
    output = ['<w:tbl><w:tblPr><w:tblStyle w:val="GuideTable"/><w:tblW w:w="9600" w:type="dxa"/></w:tblPr>']
    output.append('<w:tblGrid>' + ''.join(f'<w:gridCol w:w="{width}"/>' for _ in rows[0]) + '</w:tblGrid>')
    for index, row in enumerate(rows):
        output.append('<w:tr><w:trPr><w:cantSplit/>' + ('<w:tblHeader/>' if index == 0 else '') + '</w:trPr>')
        for cell in row:
            shade = '8B1E2D' if index == 0 else ('F6F2EF' if index % 2 == 0 else 'FFFFFF')
            output.append(f'<w:tc><w:tcPr><w:tcW w:w="{width}" w:type="dxa"/><w:shd w:fill="{shade}"/></w:tcPr>')
            output.append(paragraph(cell, 'TableHeader' if index == 0 else 'TableText') + '</w:tc>')
        output.append('</w:tr>')
    output.append('</w:tbl>' + paragraph(''))
    return ''.join(output)


lines = SOURCE.read_text(encoding='utf-8').splitlines()
body = []
index = 0
while index < len(lines):
    line = lines[index]
    if line.startswith('```'):
        index += 1
        while index < len(lines) and not lines[index].startswith('```'):
            body.append(paragraph(lines[index], 'Code'))
            index += 1
    elif line.startswith('|'):
        group = []
        while index < len(lines) and lines[index].startswith('|'):
            group.append(lines[index])
            index += 1
        body.append(table(group))
        continue
    elif line.startswith('# '):
        body.append(paragraph(line[2:], 'Title'))
    elif line.startswith('## '):
        body.append(paragraph(line[3:], 'Heading1'))
    elif line.startswith('### '):
        body.append(paragraph(line[4:], 'Heading2'))
    elif line.startswith('- '):
        body.append(paragraph('• ' + line[2:], 'ListParagraph'))
    elif re.match(r'^\d+\. ', line):
        body.append(paragraph(line, 'ListParagraph'))
    elif line.strip():
        body.append(paragraph(line))
    index += 1

document = f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="{NS}" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>
{''.join(body)}
<w:sectPr><w:footerReference w:type="default" r:id="rId2"/><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1080" w:right="1080" w:bottom="1080" w:left="1080" w:header="540" w:footer="540"/></w:sectPr>
</w:body></w:document>'''

styles = f'''<?xml version="1.0" encoding="UTF-8"?>
<w:styles xmlns:w="{NS}">
<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/><w:color w:val="282828"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="270" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>
<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>
<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:after="260"/></w:pPr><w:rPr><w:b/><w:color w:val="8B1E2D"/><w:sz w:val="44"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="300" w:after="160"/><w:outlineLvl w:val="0"/></w:pPr><w:rPr><w:b/><w:color w:val="8B1E2D"/><w:sz w:val="30"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="200" w:after="100"/><w:outlineLvl w:val="1"/></w:pPr><w:rPr><w:b/><w:color w:val="664630"/><w:sz w:val="25"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:basedOn w:val="Normal"/><w:pPr><w:ind w:left="240"/><w:spacing w:after="100"/></w:pPr></w:style>
<w:style w:type="paragraph" w:styleId="Code"><w:name w:val="Code"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="0" w:line="240"/><w:shd w:fill="F3F3F3"/></w:pPr><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="18"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="TableText"><w:name w:val="Table Text"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="70" w:line="240"/></w:pPr><w:rPr><w:sz w:val="20"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="TableHeader"><w:name w:val="Table Header"/><w:basedOn w:val="TableText"/><w:rPr><w:b/><w:color w:val="FFFFFF"/></w:rPr></w:style>
<w:style w:type="table" w:styleId="GuideTable"><w:name w:val="Guide Table"/><w:tblPr><w:tblBorders><w:bottom w:val="single" w:sz="4" w:color="DDDDDD"/><w:insideH w:val="single" w:sz="4" w:color="DDDDDD"/></w:tblBorders><w:tblCellMar><w:top w:w="90" w:type="dxa"/><w:left w:w="100" w:type="dxa"/><w:bottom w:w="90" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style>
</w:styles>'''

files = {
    '[Content_Types].xml': '''<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/></Types>''',
    '_rels/.rels': '''<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>''',
    'word/document.xml': document,
    'word/styles.xml': styles,
    'word/_rels/document.xml.rels': '''<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/></Relationships>''',
    'word/footer1.xml': f'''<?xml version="1.0" encoding="UTF-8"?><w:ftr xmlns:w="{NS}"><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:sz w:val="18"/><w:color w:val="777777"/></w:rPr><w:t xml:space="preserve">Brooks Agro ERP | System Guide | Page </w:t></w:r><w:fldSimple w:instr="PAGE"/></w:p></w:ftr>''',
}
for xml in files.values():
    ElementTree.fromstring(xml)
with ZipFile(OUTPUT, 'w', ZIP_DEFLATED) as archive:
    for name, value in files.items():
        archive.writestr(name, value)
with ZipFile(OUTPUT) as archive:
    assert archive.testzip() is None
    root = ElementTree.fromstring(archive.read('word/document.xml'))
    headings = root.findall(f'.//{{{NS}}}pStyle[@{{{NS}}}val="Heading1"]')
    assert len(headings) == 19, 'Expected contents and 18 main sections.'
print(f'Created {OUTPUT.name}: {len(SOURCE.read_text(encoding="utf-8").split()):,} source words; XML and archive checks passed.')
