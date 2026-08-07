import argparse, json
from collections import Counter, defaultdict
from datetime import date, datetime
from decimal import Decimal
from pathlib import Path
import openpyxl

def clean(v):
    if isinstance(v, (date, datetime)): return v.isoformat()
    if isinstance(v, Decimal): return float(v)
    if isinstance(v, str) and v.startswith('#'): return None
    if v is None or isinstance(v,(str,int,float,bool)): return v
    return str(v)

def rows(ws, start, end, columns):
    out=[]
    for r in range(start,end+1):
        item={name:clean(ws.cell(r,c).value) for name,c in columns.items()}
        if any(v not in (None,'') for v in item.values()): out.append(item)
    return out

def main():
    p=argparse.ArgumentParser();p.add_argument('workbook');p.add_argument('output');a=p.parse_args()
    wb=openpyxl.load_workbook(a.workbook,data_only=True,read_only=False)
    cd=wb['Consolidated_Data']
    fields={'date':1,'transaction_type':2,'account_group':3,'account':4,'amount':5,'particular':6,'business_partner':7,'department':8,'quantity':9,'unit_price':10,'transaction_id':11,'remarks':12,'employee':13,'offset_account':14,'qa_status':19,'payment_mode':20}
    transactions=[]
    for item in rows(cd,2,cd.max_row,fields):
        if item['date'] and item['transaction_type'] and item['account'] and isinstance(item['amount'],(int,float)):
            transactions.append(item)
    exact=Counter(json.dumps(x,sort_keys=True,default=str) for x in transactions)
    by_type=defaultdict(lambda:{'rows':0,'amount':0.0})
    by_account=defaultdict(lambda:{'rows':0,'amount':0.0})
    for x in transactions:
        by_type[str(x['transaction_type'])]['rows']+=1;by_type[str(x['transaction_type'])]['amount']+=float(x['amount'])
        by_account[str(x['account'])]['rows']+=1;by_account[str(x['account'])]['amount']+=float(x['amount'])

    mapping=wb['Mapping Sheet']
    master={}
    for c in range(1,mapping.max_column+1):
        heading=clean(mapping.cell(1,c).value)
        if heading: master[str(heading)]=[clean(mapping.cell(r,c).value) for r in range(2,mapping.max_row+1) if mapping.cell(r,c).value not in (None,'')]

    layouts={}
    for sheet_name, label_col, first, last in [('Profitability',2,2,97),('Balance Sheet',2,2,149),('3-Statement Model',1,2,76),('Summary',1,1,179),('Budget Forecasts',2,2,70)]:
        ws=wb[sheet_name]; layout=[]
        for r in range(first,min(last,ws.max_row)+1):
            label=clean(ws.cell(r,label_col).value)
            if label not in (None,''):
                layout.append({'row':r,'code':clean(ws.cell(r,1).value) if label_col!=1 else None,'label':label,'number_format':ws.cell(r,label_col+1).number_format,'bold':bool(ws.cell(r,label_col).font.bold),'fill':ws.cell(r,label_col).fill.fgColor.rgb})
        layouts[sheet_name]=layout

    result={'source':str(Path(a.workbook)),'transactions':transactions,'master_data':master,'report_layouts':layouts,'analysis':{
      'valid_consolidated_rows':len(transactions),'date_min':min((x['date'] for x in transactions),default=None),'date_max':max((x['date'] for x in transactions),default=None),
      'unique_transaction_ids':len({x['transaction_id'] for x in transactions if x['transaction_id'] is not None}),
      'exact_duplicate_rows':sum(n-1 for n in exact.values() if n>1),'by_type':dict(sorted(by_type.items())),'by_account':dict(sorted(by_account.items())),
      'qa_statuses':dict(Counter(str(x['qa_status']) for x in transactions)),'departments':dict(Counter(str(x['department']) for x in transactions)),
    }}
    Path(a.output).write_text(json.dumps(result,indent=2,default=clean),encoding='utf-8')
    print(json.dumps(result['analysis'],indent=2))
if __name__=='__main__':main()
