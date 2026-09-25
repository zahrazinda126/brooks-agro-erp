<?php
namespace App\Http\Controllers\Finance;
use App\Http\Controllers\Controller; use App\Models\FinancialTransaction; use App\Models\Receivable; use App\Models\Payable; use App\Models\CustomerPrepayment; use App\Models\FixedAsset; use App\Models\Budget; use App\Models\PerformanceAnalysisComment; use App\Services\BrandedReportExport; use App\Services\UnitEconomicsService; use Illuminate\Http\Request; use Illuminate\View\View; use Symfony\Component\HttpFoundation\StreamedResponse;
class FinanceReportController extends Controller {
 private function period(Request $r):array{return [$r->query('from',now()->startOfMonth()->toDateString()),$r->query('to',now()->toDateString())];}
 private function transactions(string $from,string $to){return FinancialTransaction::whereBetween('transaction_date',[$from,$to])->orderBy('transaction_date')->get();}
 private function metrics(string $from,string $to):array{$t=$this->transactions($from,$to);$income=(float)$t->filter(fn($x)=>($x->type==='money_received' && $x->transaction_category!=='customer_prepayment') || $x->type==='revenue_recognized')->sum('amount');$cashIn=(float)$t->where('type','money_received')->sum('amount');$expenses=(float)$t->where('type','money_paid')->sum('amount');return compact('income','cashIn','expenses')+['net'=>$income-$expenses,'receivables'=>(float)Receivable::sum(\DB::raw('amount-amount_paid')),'payables'=>(float)Payable::sum(\DB::raw('amount-amount_paid')),'assets'=>(float)FixedAsset::where('status','active')->sum('purchase_cost'),'budget'=>(float)Budget::whereBetween('period_start',[$from,$to])->sum('planned_amount')];}
 private function mayViewFinanceReports(Request $r): bool { $codes=$r->user()->roles->pluck('code')->push($r->user()->role?->code)->filter(); return $r->user()->canViewAllReports() || $r->user()->canAccess('finance') || $codes->contains('ceo'); }
 public function overview(Request $r):View{abort_unless($this->mayViewFinanceReports($r),403);[$from,$to]=$this->period($r);return view('finance.reports.overview',['m'=>$this->metrics($from,$to),'from'=>$from,'to'=>$to]);}
 public function kpis(Request $r):View{abort_unless($this->mayViewFinanceReports($r)||$r->user()->canAccess('kpi-dashboards'),403);[$from,$to]=$this->period($r);$m=$this->metrics($from,$to);$m['collection_rate']=Receivable::sum('amount')>0?Receivable::sum('amount_paid')/Receivable::sum('amount')*100:0;$m['payment_rate']=Payable::sum('amount')>0?Payable::sum('amount_paid')/Payable::sum('amount')*100:0;$m['budget_variance']=$m['budget']-$m['expenses'];return view('finance.reports.kpis',compact('m','from','to'));}
 public function statement(Request $r,string $report):View{abort_unless(in_array($report,['profit-loss','balance-sheet','cash-flow','trial-balance']),404);abort_unless($r->user()->canAccess($report) || $this->mayViewFinanceReports($r),403);[$from,$to]=$this->period($r);$t=$this->transactions($from,$to);$m=$this->metrics($from,$to);$rows=collect();$incomeStatement=null;$balanceSheetModel=null;
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
  elseif($report==='balance-sheet'){
   $fiscalYear=(int)$r->query('year',(\Carbon\Carbon::parse($from)->month>=7?\Carbon\Carbon::parse($from)->year:\Carbon\Carbon::parse($from)->year-1));
   $model=$this->threeStatementData($fiscalYear);
   $balanceSheetModel=['year'=>$fiscalYear,'columns'=>$model['columns'],'rows'=>collect($model['rows'])->where('section','BALANCE SHEET STATEMENT')->values()];
  }
  else{$entries=collect();foreach($t as $x){$other=$x->counterparty?:'Unclassified';if($x->transaction_category==='customer_prepayment'){$entries->push([$x->account,$x->amount,0]);$entries->push(['Customer Prepayments (Liability)',0,$x->amount]);}elseif($x->type==='revenue_recognized'){$entries->push(['Customer Prepayments (Liability)',$x->amount,0]);$entries->push(['Sales Revenue',0,$x->amount]);}elseif($x->type==='money_received'){$entries->push([$x->account,$x->amount,0]);$entries->push(['Income / '.$other,0,$x->amount]);}elseif($x->type==='money_paid'){$entries->push(['Expense / '.$other,$x->amount,0]);$entries->push([$x->account,0,$x->amount]);}else{$entries->push([$x->account,$x->amount,0]);$entries->push([$other,0,$x->amount]);}}$rows=$entries->groupBy(0)->map(fn($g,$label)=>['label'=>$label,'debit'=>$g->sum(1),'credit'=>$g->sum(2)])->values();}
  return view('finance.reports.statement',compact('report','rows','from','to','m','incomeStatement','balanceSheetModel'));}

 public function threeStatement(Request $r):View {abort_unless($this->mayViewFinanceReports($r),403);$year=(int)$r->query('year',(now()->month>=7?now()->year:now()->year-1));$model=$this->threeStatementData($year);return view('finance.reports.three-statement',compact('model','year'));}

 public function profitability(Request $r, UnitEconomicsService $economics):View
 {
  abort_unless($this->mayViewFinanceReports($r),403);
  [$from,$to]=$this->period($r);
  abort_if($from>$to,422,'The report start date must be before the end date.');
  return view('finance.reports.profitability',$economics->report($from,$to,$r->query('unit')));
 }

 public function unitCosts(Request $r, UnitEconomicsService $economics):View
 {
  abort_unless($this->mayViewFinanceReports($r),403);
  [$from,$to]=$this->period($r);
  abort_if($from>$to,422,'The report start date must be before the end date.');
  return view('finance.reports.unit-costs',$economics->report($from,$to,$r->query('unit')));
 }

 public function expenditureAnalysis(Request $r):View
 {
  abort_unless($this->mayViewFinanceReports($r),403);
  $year=(int)$r->query('year',(now()->month>=7?now()->year:now()->year-1));
  abort_unless($year>=2000&&$year<=2100,422);
  return view('finance.reports.expenditure-analysis',$this->expenditureAnalysisData($year));
 }

 public function profitAnalysis(Request $r, UnitEconomicsService $economics):View
 {
  abort_unless($this->mayViewFinanceReports($r),403);
  $year=(int)$r->query('year',(now()->month>=7?now()->year:now()->year-1));
  $quarter=max(1,min(4,(int)$r->query('quarter',1)));
  abort_unless($year>=2000&&$year<=2100,422);
  $start=\Carbon\Carbon::create($year,7,1)->addMonths(($quarter-1)*3)->startOfMonth();$end=$start->copy()->addMonths(2)->endOfMonth();
  $quarterData=$economics->report($start->toDateString(),$end->toDateString());
  $sections=collect($quarterData['accountProfitability']['sections']);
  $revenueRows=collect(data_get($sections->firstWhere('name','Revenue'),'rows',[]));
  $costRows=collect(data_get($sections->firstWhere('name','Cost of Sales'),'rows',[]));
  $expenseRows=collect(data_get($sections->firstWhere('name','Operating Costs'),'rows',[]));
  $quantities=[];$lines=\App\Models\SaleLine::whereHas('sale',fn($query)=>$query->whereBetween('sale_date',[$start,$end])->where('status','!=','void'))->get();
  foreach($lines as $line){$text=str((string)$line->category.' '.(string)$line->description)->lower();$account=match(true){$text->contains('damaged')=>'Sales Damaged Eggs',$text->contains('starter')=>'Sales Starter Eggs',$text->contains('medium')=>'Sales Medium Eggs',$text->contains('large')=>'Sales Large Eggs',$text->contains('off layer')=>'Sales Off Layers',$text->contains('manure')=>'Sales Manure',$text->contains('rabbit')&&$text->contains('meat')=>'Rabbit Meat Sales',$text->contains('goat')&&$text->contains('meat')=>'Goat Meat Sales',$text->contains('garden')=>'Garden Sales',$text->contains('rabbit')=>'Rabbit Sales',$text->contains('goat')=>'Goat Sales',default=>'Other Sales'};$quantities[$account]=($quantities[$account]??0)+(float)$line->quantity;}
  $months=collect(range(0,11))->map(function($offset)use($year,$economics){$month=\Carbon\Carbon::create($year,7,1)->addMonths($offset);$data=$economics->report($month->toDateString(),$month->copy()->endOfMonth()->toDateString());return ['month'=>$month->format('M-y'),'profit'=>(float)$data['accountProfitability']['netProfit']];});
  $annualProfit=(float)$months->sum('profit');
  return view('finance.reports.profit-analysis',compact('year','quarter','start','end','revenueRows','costRows','expenseRows','quantities','months','annualProfit'));
 }

 public function performanceAnalysis(Request $r, UnitEconomicsService $economics):View
 {
  abort_unless($this->mayViewFinanceReports($r),403);
  [$from,$to]=$this->period($r);abort_if($from>$to,422,'The report start date must be before the end date.');
  $report=$economics->report($from,$to);$sections=collect($report['accountProfitability']['sections']);
  $revenueRows=collect(data_get($sections->firstWhere('name','Revenue'),'rows',[]));$costRows=collect(data_get($sections->firstWhere('name','Cost of Sales'),'rows',[]));$expenseRows=collect(data_get($sections->firstWhere('name','Operating Costs'),'rows',[]));
  $salesRevenue=(float)$revenueRows->filter(fn($row)=>str_starts_with($row['code'],'4011'))->sum('amount');$otherIncome=(float)$revenueRows->reject(fn($row)=>str_starts_with($row['code'],'4011'))->sum('amount');
  $costOfSales=(float)$costRows->sum('amount');$financeNames=['Audit Fees','Professional Consultancy','Bad Debts','Tax Expense','Bank Charges','Fuel','Finance Expenses'];$marketingNames=['Donations'];
  $financeCosts=(float)$expenseRows->whereIn('account',$financeNames)->sum('amount');$marketing=(float)$expenseRows->whereIn('account',$marketingNames)->sum('amount');$administrative=(float)$expenseRows->reject(fn($row)=>in_array($row['account'],array_merge($financeNames,$marketingNames),true))->sum('amount');
  $budgets=Budget::where('period_start','<=',$to)->where('period_end','>=',$from)->whereNotIn('status',['rejected','cancelled'])->get();$normalise=fn($value)=>str((string)$value)->lower()->replaceMatches('/[^a-z0-9]+/','')->toString();
  $budgetFor=function($accountRows)use($budgets,$normalise){$names=collect($accountRows)->pluck('account')->map($normalise);return (float)$budgets->filter(fn($budget)=>$names->contains($normalise($budget->item)))->sum('planned_amount');};
  $salesBudget=$budgetFor($revenueRows->filter(fn($row)=>str_starts_with($row['code'],'4011')));$otherBudget=$budgetFor($revenueRows->reject(fn($row)=>str_starts_with($row['code'],'4011')));$cogsBudget=$budgetFor($costRows);$adminBudget=$budgetFor($expenseRows->reject(fn($row)=>in_array($row['account'],array_merge($financeNames,$marketingNames),true)));$marketingBudget=$budgetFor($expenseRows->whereIn('account',$marketingNames));$financeBudget=$budgetFor($expenseRows->whereIn('account',$financeNames));
  $actualRevenue=$salesRevenue+$otherIncome;$budgetRevenue=$salesBudget+$otherBudget;$actualGross=$actualRevenue-$costOfSales;$budgetGross=$budgetRevenue-$cogsBudget;$actualExpenses=$administrative+$marketing+$financeCosts;$budgetExpenses=$adminBudget+$marketingBudget+$financeBudget;$actualProfit=$actualGross-$actualExpenses;$budgetProfit=$budgetGross-$budgetExpenses;
  $rows=collect([
   ['label'=>'Revenue','actual'=>$salesRevenue,'budget'=>$salesBudget],['label'=>'Other income','actual'=>$otherIncome,'budget'=>$otherBudget],['label'=>'Total revenue','actual'=>$actualRevenue,'budget'=>$budgetRevenue,'style'=>'subtotal'],['label'=>'Cost of sales','actual'=>-$costOfSales,'budget'=>-$cogsBudget],['label'=>'Gross Profit','actual'=>$actualGross,'budget'=>$budgetGross,'style'=>'subtotal'],['label'=>'Gross Profit margin','actual'=>$actualRevenue?($actualGross/$actualRevenue)*100:0,'budget'=>$budgetRevenue?($budgetGross/$budgetRevenue)*100:0,'style'=>'percent'],['label'=>'Expenses','style'=>'heading'],['label'=>'Administrative expenses','actual'=>-$administrative,'budget'=>-$adminBudget],['label'=>'Marketing Expenses','actual'=>-$marketing,'budget'=>-$marketingBudget],['label'=>'Finance costs','actual'=>-$financeCosts,'budget'=>-$financeBudget],['label'=>'Total expenditure','actual'=>-$actualExpenses,'budget'=>-$budgetExpenses,'style'=>'subtotal'],['label'=>'Operating profit','actual'=>$actualProfit,'budget'=>$budgetProfit,'style'=>'total'],['label'=>'Operating margin','actual'=>$actualRevenue?($actualProfit/$actualRevenue)*100:0,'budget'=>$budgetRevenue?($budgetProfit/$budgetRevenue)*100:0,'style'=>'percent'],['label'=>'Profit before tax','actual'=>$actualProfit,'budget'=>$budgetProfit,'style'=>'total'],
  ])->map(function($row){$row['actual']=$row['actual']??null;$row['budget']=$row['budget']??null;$row['variance']=$row['actual']!==null&&$row['budget']!==null?$row['actual']-$row['budget']:null;$row['comments']=$row['comments']??'';return $row;});
  $comment=PerformanceAnalysisComment::with('preparedBy')->where('period_from',$from)->where('period_to',$to)->first();
  $canPrepareComment=$r->user()->canAccess('finance');
  return view('finance.reports.performance-analysis',compact('rows','from','to','comment','canPrepareComment'));
 }

 public function savePerformanceAnalysisComment(Request $r)
 {
  abort_unless($r->user()->canAccess('finance'),403);
  $data=$r->validate(['from'=>['required','date'],'to'=>['required','date','after_or_equal:from'],'comment'=>['nullable','string','max:5000']]);
  PerformanceAnalysisComment::updateOrCreate(['period_from'=>$data['from'],'period_to'=>$data['to']],['comment'=>$data['comment'],'prepared_by'=>$r->user()->id]);
  return redirect()->route('finance.reports.performance-analysis',['from'=>$data['from'],'to'=>$data['to']])->with('success','Management commentary saved for this reporting period.');
 }

 private function expenditureAnalysisData(int $year):array
 {
  $accounts=collect(config('finance_entry.profitability_accounts.Operating Costs', []));
  $aliases=['Staff Medication'=>'Staff Welfare Related Costs','Condolence Support'=>'Staff Welfare Related Costs','Medical Support'=>'Staff Welfare Related Costs','Emergency Support'=>'Staff Welfare Related Costs','Other Staff Welfare'=>'Staff Welfare Related Costs','Casual Wages'=>'Salary Expense','Staff Reimbursement'=>'Other Expenses'];
  $build=function(int $fiscalYear)use($accounts,$aliases){
   $months=collect(range(0,11))->map(fn($i)=>\Carbon\Carbon::create($fiscalYear,7,1)->addMonths($i));
   $transactions=FinancialTransaction::where('type','money_paid')->whereBetween('transaction_date',[$months->first()->toDateString(),$months->last()->copy()->endOfMonth()->toDateString()])->get();
   $rows=$accounts->map(function($name,$code)use($months,$transactions,$aliases){
    $values=$months->map(function($month)use($transactions,$name,$aliases){return (float)$transactions->filter(function($transaction)use($month,$name,$aliases){$account=$aliases[$transaction->entry_account]??$transaction->entry_account;return $account===$name&&\Carbon\Carbon::parse($transaction->transaction_date)->isSameMonth($month);})->sum('amount');})->all();
    return ['code'=>(string)$code,'account'=>$name,'values'=>$values,'total'=>array_sum($values)];
   })->values();
   $totals=array_map(fn($i)=>(float)$rows->sum(fn($row)=>$row['values'][$i]),range(0,11));
   return ['months'=>$months->map(fn($month)=>$month->format('M-y'))->all(),'rows'=>$rows,'totals'=>$totals,'annual'=>array_sum($totals)];
  };
  $current=$build($year);$previous=$build($year-1);
  $movement=[];foreach($current['totals'] as $i=>$total){$base=$i===0?$previous['totals'][11]:$current['totals'][$i-1];$movement[]=$base==0?null:(($total-$base)/abs($base))*100;}
  return compact('year','current','previous','movement');
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
  abort_unless($this->mayViewFinanceReports($r),403);
  $allowed=['overview','kpis','profit-loss','balance-sheet','cash-flow','trial-balance','three-statement','profitability','unit-costs','expenditure-analysis','profit-analysis','performance-analysis'];abort_unless(in_array($report,$allowed,true),404);
  $title=str($report)->replace('-',' ')->title()->toString();
  if($report==='three-statement'){
   $year=(int)$r->query('year',(now()->month>=7?now()->year:now()->year-1));$data=$this->threeStatementData($year);
   $rows=collect($data['rows'])->map(fn($x)=>['cells'=>array_merge([$x['section'],$x['label']],$x['values'],[$x['total']]),'style'=>$x['style']]);
   return app(BrandedReportExport::class)->download('Three-Statement Financial Model','Financial year July '.$year.' to June '.($year+1),array_merge(['Section','Account'],$data['columns'],['Annual Total']),$rows,'three-statement-'.$year.'.xls',$r->user()->displayName());
  }
  if($report==='profitability'){
   $view=$this->profitability($r,app(UnitEconomicsService::class))->getData();$headers=['Section','Code','Account','Amount (UGX)'];$rows=collect($view['accountProfitability']['sections'])->flatMap(fn($section)=>collect($section['rows'])->map(fn($row)=>[$section['name'],$row['code'],$row['account'],$row['amount']])->push([$section['name'],'','Total '.$section['name'],$section['total']]));
   return app(BrandedReportExport::class)->download('Profitability','Reporting period: '.$view['from'].' to '.$view['to'],$headers,$rows,'profitability-'.now()->format('Ymd-His').'.xls',$r->user()->displayName());
  }
  if($report==='unit-costs'){
   $view=$this->unitCosts($r,app(UnitEconomicsService::class))->getData();$headers=['Farm unit','Sales revenue (UGX)','Direct spending (UGX)','Result before shared costs (UGX)'];$rows=collect($view['units'])->map(fn($row)=>[$row['unit'],$row['revenue'],$row['spent'],$row['result']]);
   return app(BrandedReportExport::class)->download('Unit Costs','Reporting period: '.$view['from'].' to '.$view['to'],$headers,$rows,'unit-costs-'.now()->format('Ymd-His').'.xls',$r->user()->displayName());
  }
  if($report==='expenditure-analysis'){
   $view=$this->expenditureAnalysis($r)->getData();$headers=array_merge(['Code','Account'],$view['current']['months'],['Annual total (UGX)']);$rows=collect($view['current']['rows'])->map(fn($row)=>array_merge([$row['code'],$row['account']],$row['values'],[$row['total']]));
   return app(BrandedReportExport::class)->download('Expenditure Analysis','Financial year July '.$view['year'].' to June '.($view['year']+1),$headers,$rows,'expenditure-analysis-'.$view['year'].'.xls',$r->user()->displayName());
  }
  if($report==='profit-analysis'){
   $view=$this->profitAnalysis($r,app(UnitEconomicsService::class))->getData();$headers=['Section','Account','Amount (UGX)'];$rows=collect([['Revenue',$view['revenueRows']],['Cost of Sales',$view['costRows']],['Expenditure',$view['expenseRows']]])->flatMap(fn($section)=>collect($section[1])->map(fn($row)=>[$section[0],$row['account'],$row['amount']]));
   return app(BrandedReportExport::class)->download('Profit Analysis','Quarter '.$view['quarter'].' of financial year '.$view['year'],$headers,$rows,'profit-analysis-'.$view['year'].'-q'.$view['quarter'].'.xls',$r->user()->displayName());
  }
  if($report==='performance-analysis'){
   $view=$this->performanceAnalysis($r,app(UnitEconomicsService::class))->getData();$headers=['Summary item','Actual (UGX)','Budget (UGX)','Variance (UGX)'];$rows=collect($view['rows'])->map(fn($row)=>[$row['label'],$row['actual'],$row['budget'],$row['variance']]);$rows->push(['Management commentary',$view['comment']?->comment ?? '','','']);
   return app(BrandedReportExport::class)->download('P&L Performance Analysis','Reporting period: '.$view['from'].' to '.$view['to'],$headers,$rows,'performance-analysis-'.now()->format('Ymd-His').'.xls',$r->user()->displayName());
  }
  [$from,$to]=$this->period($r);$m=$this->metrics($from,$to);
  if(in_array($report,['overview','kpis'])){$headers=['Metric','Amount / Rate'];$rows=collect($m)->map(fn($v,$k)=>[str($k)->replace('_',' ')->title()->toString(),$v]);}
  else{$view=$this->statement($r,$report)->getData();if($report==='profit-loss'){$headers=['Income Statement Section','Amount (UGX)'];$s=$view['incomeStatement'];$rows=collect([['Revenue',$s['revenue']],['Cost of Sales',$s['costOfSales']],['Gross Profit',$s['grossProfit']],['Operating Costs',$s['operatingCosts']],[$s['netProfit']>=0?'Net Profit':'Net Loss',abs($s['netProfit'])]]);}elseif($report==='balance-sheet'){$sheet=$view['balanceSheetModel'];$headers=array_merge(['Account'],$sheet['columns'],['Total to date']);$rows=$sheet['rows']->map(fn($x)=>array_merge([$x['label']],$x['values'],[$x['total']]));}else{$headers=['Account / Section','Debit / Asset','Credit / Liability'];$rows=collect($view['rows'])->map(fn($x)=>[$x['label'],$x['debit'],$x['credit']]);}}
  return app(BrandedReportExport::class)->download($title,'Reporting period: '.$from.' to '.$to,$headers,$rows,$report.'-'.now()->format('Ymd-His').'.xls',$r->user()->displayName());
 }
}
