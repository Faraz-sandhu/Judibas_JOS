from pathlib import Path
from html.parser import HTMLParser
from xml.sax.saxutils import escape
from zipfile import ZipFile,ZIP_DEFLATED
import json
base=Path('docs/frappe-accounting');pages=json.loads((base/'guide-content.json').read_text(encoding='utf-8'))
class Node:
 def __init__(self,tag='',attrs=None):self.tag=tag;self.attrs=dict(attrs or []);self.children=[]
 def text(self):return ''.join(c.text() if isinstance(c,Node) else c for c in self.children)
class Parse(HTMLParser):
 def __init__(self):super().__init__();self.root=Node('root');self.stack=[self.root]
 def handle_starttag(self,t,a):
  n=Node(t,a);self.stack[-1].children.append(n)
  if t not in ['br','img','hr','meta']:self.stack.append(n)
 def handle_endtag(self,t):
  for i in range(len(self.stack)-1,0,-1):
   if self.stack[i].tag==t:self.stack=self.stack[:i];break
 def handle_data(self,d):self.stack[-1].children.append(d)
def para(text,style='Normal',break_before=False):
 return '<w:p><w:pPr><w:pStyle w:val="'+style+'"/>'+('<w:pageBreakBefore/>' if break_before else '')+'</w:pPr><w:r><w:t xml:space="preserve">'+escape(text)+'</w:t></w:r></w:p>'
def draw(rid):
 return f'''<w:p><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="5900000" cy="6785000"/><wp:docPr id="{rid}" name="Accounting tree {rid}"/><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="{rid}" name="Tree diagram"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="rId{rid}"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="5900000" cy="6785000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>'''
def walk(node,page_index):
 out=''
 for n in node.children:
  if isinstance(n,str):continue
  if n.tag=='figure':out+=draw(1 if page_index==2 else 2)
  elif n.tag in ['p','h3','li']:
   style='Heading2' if n.tag=='h3' else 'Small' if n.attrs.get('class') in ['refs','caption'] or page_index==16 else 'Normal'
   out+=para(('- ' if n.tag=='li' else '')+n.text(),style)
  elif n.tag=='table':
   rows=[]
   def collect(n):
    for c in n.children:
     if isinstance(c,Node):
      if c.tag=='tr':rows.append(c)
      else:collect(c)
   collect(n)
   out+='<w:tbl><w:tblPr><w:tblStyle w:val="Grid"/><w:tblW w:w="0" w:type="auto"/></w:tblPr>'
   for row in rows:
    out+='<w:tr>'
    for c in row.children:
     if isinstance(c,Node) and c.tag in ['th','td']:
      out+='<w:tc><w:tcPr><w:tcW w:w="3000" w:type="dxa"/>'+('<w:shd w:fill="ECEAF8"/>' if c.tag=='th' else '')+'</w:tcPr>'+para(c.text(),'TableText')+'</w:tc>'
    out+='</w:tr>'
   out+='</w:tbl>'+para('')
  elif n.tag=='svg':continue
  elif n.attrs.get('class') in ['cover-title','cover-tags']:out+=para(n.text(),'Subtitle')
  else:out+=walk(n,page_index)
 return out
body=''
for i,page in enumerate(pages):
 body+=para(page['kicker'],'Kicker',i>0)+para(page['title'],'Heading1')
 parser=Parse();parser.feed(page['body']);body+=walk(parser.root,i)
ns='xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
document=f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document {ns}><w:body>'+body+'<w:sectPr><w:footerReference w:type="default" r:id="rId3"/><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="850" w:right="850" w:bottom="850" w:left="850" w:header="400" w:footer="400"/></w:sectPr></w:body></w:document>'
styles='''<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:sz w:val="21"/><w:color w:val="253047"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="150" w:line="290" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'''
for name,size,color in [('Normal',21,'253047'),('Heading1',48,'253047'),('Heading2',27,'323D58'),('Subtitle',36,'6554CC'),('Kicker',17,'6554CC'),('Small',16,'66738A'),('TableText',18,'253047')]:
 styles+=f'<w:style w:type="paragraph" w:styleId="{name}"><w:name w:val="{name}"/><w:pPr>'+('<w:keepNext/>' if name.startswith('Heading') or name=='Kicker' else '')+f'</w:pPr><w:rPr><w:sz w:val="{size}"/><w:color w:val="{color}"/>'+('<w:b/>' if name.startswith('Heading') else '')+'</w:rPr></w:style>'
styles+='<w:style w:type="table" w:styleId="Grid"><w:name w:val="Grid"/><w:tblPr><w:tblBorders>'+''.join(f'<w:{n} w:val="single" w:sz="4" w:color="DFE4EF"/>' for n in ['top','left','bottom','right','insideH','insideV'])+'</w:tblBorders><w:tblCellMar><w:top w:w="100" w:type="dxa"/><w:left w:w="100" w:type="dxa"/><w:bottom w:w="100" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style></w:styles>'
types='''<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/></Types>'''
rels='<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'+''.join(f'<Relationship Id="rId{i}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/tree{i}.png"/>' for i in [1,2])+'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/><Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>'
footer='<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'+para('JUDIBAS / FRAPPE ACCOUNTING REFERENCE','Small')+'</w:ftr>'
with ZipFile(base/'Frappe-Accounting-Guide.docx','w',ZIP_DEFLATED) as z:
 for name,value in {'[Content_Types].xml':types,'_rels/.rels':'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>','word/document.xml':document,'word/styles.xml':styles,'word/_rels/document.xml.rels':rels,'word/footer1.xml':footer}.items():z.writestr(name,value.encode('utf-8'))
 for i,name in enumerate(['feature-tree','accounting-workflow-tree'],1):z.write(base/(name+'.png'),f'word/media/tree{i}.png')
# Check every XML part and the embedded diagrams.
import xml.etree.ElementTree as ET
with ZipFile(base/'Frappe-Accounting-Guide.docx') as z:
 assert z.testzip() is None
 for name in z.namelist():
  if name.endswith(('.xml','.rels')):ET.fromstring(z.read(name))
print('Editable DOCX created and XML/package integrity verified.')
