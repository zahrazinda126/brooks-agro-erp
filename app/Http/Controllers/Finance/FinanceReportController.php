<?php
namespace App\Http\Controllers\Finance;
use App\Http\Controllers\Controller; use App\Models\FinancialTransaction; use App\Models\Receivable; use App\Models\Payable; use App\Models\CustomerPrepayment; use App\Models\FixedAsset; use App\Models\Budget; use App\Services\BrandedReportExport; use App\Services\UnitEconomicsService; use Illuminate\Http\Request; use Illuminate\View\View; use Symfony\Component\HttpFoundation\StreamedResponse;
class FinanceReportController extends Controller {
 private function period(Request $r):array{return [$r->query('from',now()->startOfMonth()->toDateString()),$r->query('to',now()->toDateString())];}
 private function transactions(string $from,string $to){return FinancialTransaction::whereBetween('transaction_date',[$from,$to])->orderBy('transaction_date')->get();}
 private function metrics(string $from,string $to):array{$t=$this->transactions($from,$to);$income=(float)$t->filter(fn($x)=>($x->type==='money_received' && $x->transaction_category!=='customer_prepayment') || $x->type==='revenue_recognized')->sum('amount');$cashIn=(float)$t->where('type','money_received')->sum('amount');$expenses=(float)$t->where('type','money_paid')->sum('amount');return compact('income','cashIn','expenses')+['net'=>$income-$expenses,'receivables'=>(float)Receivable::sum(\DB::raw('amount-amount_paid')),'payables'=>(float)Payable::sum(\DB::raw('amount-amount_paid')),'assets'=>(float)FixedAsset::where('status','active')->sum('purchase_cost'),'budget'=>(float)Budget::whereBetween('period_start',[$from,$to])->sum('planned_amount')];}
 public function overview(Request $r):View{abort_unless($r->user()->canViewAllReports(),403);[$from,$to]=$this->period($r);return view('finance.reports.overview',['m'=>$this->metrics($from,$to),'from'=>$from,'to'=>$to]);}
 public function kpis(Request $r):View{abort_unless($r->user()->canViewAllReports()||$r->user()->canAccess('kpi-dashboards'),403);[$from,$to]=$this->period($r);$m=$this->metrics($from,$to);$m['collection_rate']=Receivable::sum('amount')>0?Receivable::sum('amount_paid')/Receivable::sum('amount')*100:0;$m['payment_rate']=Payable::sum('amount')>0?Payable::sum('amount_paid')/Payable::sum('amount')*100:0;$m['budget_variance']=$m['budget']-$m['expenses'];return view('finance.reports.kpis',compact('m','from','to'));}
 public function statement(Request $r,string $report):View{abort_unless(in_array($report,['profit-loss','balance-sheet','cash-flow','trial-balance']),404);abort_unless($r->user()->canAccess($report) || $r->user()->canViewAllReports() || $r->user()->canAccess('finance'),403);[$from,$to]=$this->period($r);$t=$this->transactions($from,$to);$m=$this->metrics($from,$to);$rows=collect();$incomeStatement=null;
  if($report==='profit-loss'){
   $revenue=(float)$t->filter(fn($x)=>(($x->type==='money_received'&&$x->transaction_category!=='customer_prepayment')||$x->type==='revenue_recognized'))->sum('amount');
   $expenses=$t->where('type','money_paid')->filter(fn($x)=>$x->transaction_category==='purchase'||in_array($x->account_group,['Cost of Sales','Operating Costs'],true));
   $costOfSales=(float)$expenses->where('account_group','Cost of Sales')->sum('amount');
   $operatingCosts=(float)$expenses->reject(fn($x)=>$x->account_group==='Cost of Sales')->sum('amount');
   $grossProfit=$revenue-$costOfSales;$netProfit=$grossProfit-$operatingCosts;
   $incomeStatement=compact('revenue','costOfSales','grossProfit','operatingCosts','netProfit');$m['net']=$netProfit;
   $rows=collect([['label'=>'Revenue','debit'=>0,'credit'=>$revenue],['label'=>'Cost of Sales','debit'=>$costOfSales,'credit'=>0],['label'=>'Gross Profit','debit'=>$grossProfit<0?abs($grossProfit):0,'credit'=>$grossProfit>=0?$grossProfit:0],['label'=>'Operating Costs','debit'=>$operatingCosts,'credit'=>0],['label'=>$netProfit>=0?'Net Profit':'Net Loss','debit'=>$netProfit<0?abs($netProfit):0,'credit'=>$netProfit>=0?$netProfit:0]]);
  }
  elseif($report==='cash-flow'){$rows=$t->whereIn('type',['money_received','money_paid'])->groupBy(fn($x)=>$x->type==='money_received'?'Cash inflows':'Cash outflows')->map(fn($g,$label)=>['label'=>$label,'debit'=>$label==='Cash inflows'?$g->sum('amount'):0,'credit'=>$label==='Cash outflows'?$g->sum('amount'):0])->values();}
  elseif($report==='balance-sheet'){$pre=(float)CustomerPrepayment::sum(\DB::raw('amount-amount_used'));$rows=collect([['label'=>'Recorded cash and bank balance','debit'=>$m['cashIn']-$m['expenses'],'credit'=>0],['label'=>'Accounts receivable','debit'=>$m['receivables'],'credit'=>0],['label'=>'Fixed assets at cost','debit'=>$m['assets'],'credit'=>0],['label'=>'Accounts payable','debit'=>0,'credit'=>$m['payables']],['label'=>'Customer prepayments','debit'=>0,'credit'=>$pre]]);$equity=$rows->sum('debit')-$rows->sum('credit');$rows->push(['label'=>'Net recorded assets / equity','debit'=>0,'credit'=>$equity]);}
  else{$entries=collect();foreach($t as $x){$other=$x->counterparty?:'Unclassified';if($x->transaction_category==='customer_prepayment'){$entries->push([$x->account,$x->amount,0]);$entries->push(['Customer Prepayments (Liability)',0,$x->amount]);}elseif($x->type==='revenue_recognized'){$entries->push(['Customer Prepayments (Liability)',$x->amount,0]);$entries->push(['Sales Revenue',0,$x->amount]);}elseif($x->type==='money_received'){$entries->push([$x->account,$x->amount,0]);$entries->push(['Income / '.$other,0,$x->amount]);}elseif($x->type==='money_paid'){$entries->push(['Expense / '.$other,$x->amount,0]);$entries->push([$x->account,0,$x->amount]);}else{$entries->push([$x->account,$x->amount,0]);$entries->push([$other,0,$x->amount]);}}$rows=$entries->groupBy(0)->map(fn($g,$label)=>['label'=>$label,'debit'=>$g->sum(1),'credit'=>$g->sum(2)])->values();}
  return view('finance.reports.statement',compact('report','rows','from','to','m','incomeStatement'));}

 public function threeStatement(Request $r):View {abort_unless($r->user()->canViewAllReports()||$r->user()->canAccess('finance'),403);$year=(int)$r->query('year',(now()->month>=7?now()->year:now()->year-1));$model=$this->threeStatementData($year);return view('finance.reports.three-statement',compact('model','year'));}

 public function profitability(Request $r, UnitEconomicsService $economics):View
 {
  abort_unless($r->user()->canViewAllReports()||$r->user()->canAccess('finance'),403);
  [$from,$to]=$this->period($r);
  abort_if($from>$to,422,'The report start date must be before the end date.');
  return view('finance.reports.profitability',$economics->report($from,$to,$r->query('unit')));
 }

 private function threeStatementData(int $year): array
 {
  $months=collect(range(0,11))->map(fn($i)=>now()->setDate($year,7,1)->startOfMonth()->addMonths($i));
  $columns=$months->map(fn($d)=>$d->format('M Y'))->all();
  $monthly=$months->map(function($d){
   $t=$this->transactions($d->toDateString(),$d->copy()->endOfMonth()->toDateString());
   $received=(float)$t->filter(fn($x)=>($x->type==='money_received' && $x->transaction_category!=='customer_prepayment') || $x->type==='revenue_recognized')->sum('amount');
   $cashReceived=(float)$t->where('type','money_received')->sum('amount');
   $paid=$t->where('type','money_paid');
   $matches=fn($x,$words)=>str($x->description.' '.$x->counterparty)->lower()->contains($words);
   $cogs=(float)$paid->filter(fn($x)=>$matches($x,['feed','medicine','medication','vaccine','vaccination','husks','crop husbandry','direct purchase']))->sum('amount');
   $badDebts=(float)$paid->filter(fn($x)=>$matches($x,['bad debt']))->sum('amount');
   $tax=(float)$paid->filter(fn($x)=>$matches($x,['tax','paye']))->sum('amount');
   $operating=(float)$paid->sum('amount')-$cogs-$badDebts-$tax;
   $depreciation=0.0;$gross=$received-$cogs;$profitBeforeTax=$gross-$operating-$depreciation-$badDebts;$net=$profitBeforeTax-$tax;
   return compact('received','cashReceived','cogs','gross','operating','depreciation','badDebts','profitBeforeTax','tax','net');
  });
  $z=array_fill(0,12,0.0);$values=fn($key)=>$monthly->pluck($key)->map(fn($x)=>(float)$x)->all();
  $cumulative=function($items){$running=0;return array_map(function($x)use(&$running){return $running+=(float)$x;},$items);};
  $ppe=[];$land=[];$receivables=[];$payables=[];$unearned=[];
  foreach($months as $d){$end=$d->copy()->endOfMonth();$ppe[]=(float)FixedAsset::where('category','!=','Land')->where('purchase_date','<=',$end)->sum('purchase_cost');$land[]=(float)FixedAsset::where('category','Land')->where('purchase_date','<=',$end)->sum('purchase_cost');$receivables[]=(float)Receivable::where('invoice_date','<=',$end)->sum(\DB::raw('amount-amount_paid'));$payables[]=(float)Payable::where('invoice_date','<=',$end)->sum(\DB::raw('amount-amount_paid'));$unearned[]=(float)CustomerPrepayment::where('received_date','<=',$end)->sum(\DB::raw('amount-amount_used'));}
  $cash=$cumulative(array_map(fn($i)=>$monthly[$i]['cashReceived']-$monthly[$i]['cogs']-$monthly[$i]['operating']-$monthly[$i]['badDebts']-$monthly[$i]['tax'],range(0,11)));
  $inventory=$z;$otherReceivables=$z;$otherAssets=$z;$loans=$z;$lease=$z;$contributions=$z;$deferredTax=$z;$capital=$z;$reserves=$z;$drawings=$z;
  $totalNca=array_map(fn($a,$b)=>$a+$b,$ppe,$land);$totalCa=array_map(fn($a,$b,$c,$d)=>$a+$b+$c+$d,$cash,$receivables,$inventory,$otherReceivables);$totalAssets=array_map(fn($a,$b,$c)=>$a+$b+$c,$totalNca,$totalCa,$otherAssets);
  $totalNcl=array_map(fn($a,$b,$c,$d)=>$a+$b+$c+$d,$loans,$lease,$contributions,$deferredTax);$totalCl=array_map(fn($a,$b)=>$a+$b,$payables,$unearned);$totalLiabilities=array_map(fn($a,$b)=>$a+$b,$totalNcl,$totalCl);
  $shareholderFunds=array_map(fn($a,$l,$c,$r)=>$a-$l-$c-$r,$totalAssets,$totalLiabilities,$capital,$reserves);$totalEquity=array_map(fn($a,$b,$c)=>$a+$b+$c,$capital,$reserves,$shareholderFunds);$totalLe=array_map(fn($a,$b)=>$a+$b,$totalLiabilities,$totalEquity);
  $change=function($series,$asset=false){$out=[];$previous=0;foreach($series as $current){$delta=$current-$previous;$out[]=$asset?-$delta:$delta;$previous=$current;}return $out;};
  $netIncome=$values('net');$dep=$values('depreciation');$bad=$values('badDebts');$changeAr=$change($receivables,true);$changeInventory=$change($inventory,true);$changeAp=$change($payables);$changeUnearned=$change($unearned);$changeOther=$z;
  $cfo=array_map(fn($a,$b,$c,$d,$e,$f,$g,$h)=>$a+$b+$c+$d+$e+$f+$g+$h,$netIncome,$dep,$bad,$changeAr,$changeInventory,$changeAp,$changeUnearned,$changeOther);
  $ppeChanges=$change(array_map(fn($a,$b)=>$a+$b,$ppe,$land),true);$loanChanges=$change($loans);$contributionChanges=$change($contributions);$deferredChanges=$change($deferredTax);$cfi=array_map(fn($a,$b,$c,$d)=>$a+$b+$c+$d,$ppeChanges,$loanChanges,$contributionChanges,$deferredChanges);
  $capitalChanges=$change($capital);$dividends=array_map(fn($x)=>-$x,$drawings);$equityChanges=$z;$cff=array_map(fn($a,$b,$c)=>$a+$b+$c,$capitalChanges,$dividends,$equityChanges);
  $beginning=[];$changes=[];$ending=[];$balance=0;for($i=0;$i<12;$i++){$beginning[]=$balance;$changes[]=$cfo[$i]+$cfi[$i]+$cff[$i];$balance+=$changes[$i];$ending[]=$balance;}
  $rows=[];$add=function($section,$label,$items,$style='detail',$annual='sum')use(&$rows){$rows[]=['section'=>$section,'label'=>$label,'values'=>$items,'total'=>$annual==='ending'?(end($items)?:0):array_sum($items),'style'=>$style];};
  $add('INCOME STATEMENT','Revenue',$values('received'));$add('INCOME STATEMENT','Cost of Goods Sold',$values('cogs'));$add('INCOME STATEMENT','Gross Profit',$values('gross'),'total');$add('INCOME STATEMENT','Operating Expenses',$values('operating'));$add('INCOME STATEMENT','Depreciation',$dep);$add('INCOME STATEMENT','Bad Debts',$bad);$add('INCOME STATEMENT','Profit Before Tax',$values('profitBeforeTax'),'total');$add('INCOME STATEMENT','Tax',$values('tax'));$add('INCOME STATEMENT','NET PROFIT',$netIncome,'final');
  $add('BALANCE SHEET STATEMENT','NCA (Non-Current Assets)',$z,'heading');$add('BALANCE SHEET STATEMENT','Property Plant and Equipment (PPE)',$ppe,'detail','ending');$add('BALANCE SHEET STATEMENT','Land',$land,'detail','ending');$add('BALANCE SHEET STATEMENT','Total NCA',$totalNca,'subtotal','ending');$add('BALANCE SHEET STATEMENT','CA (Current Assets)',$z,'heading');$add('BALANCE SHEET STATEMENT','Cash and Cash Equivalents',$cash,'detail','ending');$add('BALANCE SHEET STATEMENT','Accounts Receivable',$receivables,'detail','ending');$add('BALANCE SHEET STATEMENT','Inventory',$inventory,'detail','ending');$add('BALANCE SHEET STATEMENT','Other Receivables',$otherReceivables,'detail','ending');$add('BALANCE SHEET STATEMENT','Total CA',$totalCa,'subtotal','ending');$add('BALANCE SHEET STATEMENT','Total Assets',$totalAssets,'total','ending');
  $add('BALANCE SHEET STATEMENT','NCL (Non-Current Liabilities)',$z,'heading');$add('BALANCE SHEET STATEMENT','Loans',$loans,'detail','ending');$add('BALANCE SHEET STATEMENT','Lease Liability',$lease,'detail','ending');$add('BALANCE SHEET STATEMENT','Un_Earned_Contribution',$contributions,'detail','ending');$add('BALANCE SHEET STATEMENT','Deferred Tax',$deferredTax,'detail','ending');$add('BALANCE SHEET STATEMENT','Total NCL',$totalNcl,'subtotal','ending');$add('BALANCE SHEET STATEMENT','CL (Current Liabilities)',$z,'heading');$add('BALANCE SHEET STATEMENT','Accounts Payable',$payables,'detail','ending');$add('BALANCE SHEET STATEMENT','Un_Earned_Income (customers)',$unearned,'detail','ending');$add('BALANCE SHEET STATEMENT','Total CL',$totalCl,'subtotal','ending');$add('BALANCE SHEET STATEMENT','Total Liabilities',$totalLiabilities,'total','ending');$add('BALANCE SHEET STATEMENT',"Owner's Equity",$z,'heading');$add('BALANCE SHEET STATEMENT','Capital',$capital,'detail','ending');$add('BALANCE SHEET STATEMENT','Reserves',$reserves,'detail','ending');$add('BALANCE SHEET STATEMENT','Shareholder funds',$shareholderFunds,'detail','ending');$add('BALANCE SHEET STATEMENT',"Total Owner's Equity",$totalEquity,'total','ending');$add('BALANCE SHEET STATEMENT',"TOTAL LIABILITIES + OWNER'S EQUITY",$totalLe,'final','ending');
  $add('CASHFLOW STATEMENT','Operating Activities',$z,'heading');$add('CASHFLOW STATEMENT','Net Income',$netIncome);$add('CASHFLOW STATEMENT','ADD: Depreciation',$dep);$add('CASHFLOW STATEMENT','ADD: Bad Debts',$bad);$add('CASHFLOW STATEMENT','Changes in Account Receivable',$changeAr);$add('CASHFLOW STATEMENT','Changes in Inventory',$changeInventory);$add('CASHFLOW STATEMENT','Changes in Accounts Payable',$changeAp);$add('CASHFLOW STATEMENT','Changes in UnEarned_Income (Customers)',$changeUnearned);$add('CASHFLOW STATEMENT','Changes in Other Assets & Liabilities',$changeOther);$add('CASHFLOW STATEMENT','Cashflow from Operating Activities',$cfo,'total');$add('CASHFLOW STATEMENT','Investing Activities',$z,'heading');$add('CASHFLOW STATEMENT','Changes in PPE & Land (Acquisitions,Sales,Disposals)',$ppeChanges);$add('CASHFLOW STATEMENT','Changes in Loans',$loanChanges);$add('CASHFLOW STATEMENT','Changes in Un_earned_Contributions',$contributionChanges);$add('CASHFLOW STATEMENT','Changes in Deferred Tax',$deferredChanges);$add('CASHFLOW STATEMENT','Cashflow from Investing Activities',$cfi,'total');$add('CASHFLOW STATEMENT','Financing Activities',$z,'heading');$add('CASHFLOW STATEMENT','Changes in capital (Additions,Drawings)',$capitalChanges);$add('CASHFLOW STATEMENT','Dividends Pay-Off to shares',$dividends);$add('CASHFLOW STATEMENT','Changes equities (sale off)',$equityChanges);$add('CASHFLOW STATEMENT','Cashflow from Financing Activities',$cff,'total');$add('CASHFLOW STATEMENT','Beginning Cash Balance',$beginning,'heading','ending');$add('CASHFLOW STATEMENT','Changes in cash',$changes);$add('CASHFLOW STATEMENT','END OF MONTH CASH BALANCE',$ending,'final','ending');
  return compact('columns','rows');
 }

 public function export(Request $r,string $report):StreamedResponse
 {
  abort_unless($r->user()->canViewAllReports()||$r->user()->canAccess('finance'),403);
  $allowed=['overview','kpis','profit-loss','balance-sheet','cash-flow','trial-balance','three-statement'];abort_unless(in_array($report,$allowed,true),404);
  $title=str($report)->replace('-',' ')->title()->toString();
  if($report==='three-statement'){
   $year=(int)$r->query('year',(now()->month>=7?now()->year:now()->year-1));$data=$this->threeStatementData($year);
   $rows=collect($data['rows'])->map(fn($x)=>['cells'=>array_merge([$x['section'],$x['label']],$x['values'],[$x['total']]),'style'=>$x['style']]);
   return app(BrandedReportExport::class)->download('Three-Statement Financial Model','Financial year July '.$year.' to June '.($year+1),array_merge(['Section','Account'],$data['columns'],['Annual Total']),$rows,'three-statement-'.$year.'.xls',$r->user()->displayName());
  }
  [$from,$to]=$this->period($r);$m=$this->metrics($from,$to);
  if(in_array($report,['overview','kpis'])){$headers=['Metric','Amount / Rate'];$rows=collect($m)->map(fn($v,$k)=>[str($k)->replace('_',' ')->title()->toString(),$v]);}
  else{$view=$this->statement($r,$report)->getData();if($report==='profit-loss'){$headers=['Income Statement Section','Amount (UGX)'];$s=$view['incomeStatement'];$rows=collect([['Revenue',$s['revenue']],['Cost of Sales',$s['costOfSales']],['Gross Profit',$s['grossProfit']],['Operating Costs',$s['operatingCosts']],[$s['netProfit']>=0?'Net Profit':'Net Loss',abs($s['netProfit'])]]);}else{$headers=['Account / Section','Debit / Asset','Credit / Liability'];$rows=collect($view['rows'])->map(fn($x)=>[$x['label'],$x['debit'],$x['credit']]);}}
  return app(BrandedReportExport::class)->download($title,'Reporting period: '.$from.' to '.$to,$headers,$rows,$report.'-'.now()->format('Ymd-His').'.xls',$r->user()->displayName());
 }
}
