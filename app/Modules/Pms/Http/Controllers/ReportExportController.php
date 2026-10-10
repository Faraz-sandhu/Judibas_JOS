<?php
namespace App\Modules\Pms\Http\Controllers;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
class ReportExportController extends Controller {
 public function download(Request $request,string $format){
  abort_unless(in_array($format,['csv','xlsx','pdf'],true),404);
  $request->merge(['start'=>0,'length'=>2147483647]);
  $rows=app(ReportController::class)->getReportData($request)->getData(true)['data'];
  $columns=['company_name'=>'Company','project_name'=>'Project','department_name'=>'Department','title'=>'Title','type'=>'Type','status'=>'Status','deadline_health'=>'Deadline health','due_date'=>'Due date','assignee'=>'Assignee','worked_by'=>'Worked by','invest_time'=>'Time spent'];
  return $this->render($format,$rows,$columns,'pms-report');
 }
 public function workLogs(Request $request,string $format){
  $request->headers->set('X-Requested-With','XMLHttpRequest');
  $rows=app(SummaryController::class)->index($request)->getData(true)['data'];
  return $this->render($format,$rows,['project'=>'Project','type'=>'Type','title'=>'Title','status'=>'Status','user'=>'Employee','time_spent'=>'Time spent','due_date'=>'Due date','created_at'=>'Created','work_date'=>'Work date','assigned_by'=>'Assigned by'],'pms-work-logs');
 }
 private function render(string $format,array $rows,array $columns,string $filename){
  abort_unless(in_array($format,['csv','xlsx','pdf'],true),404);
  $data=[array_values($columns)];foreach($rows as $row){$values=[];foreach($columns as $key=>$label)$values[]=strip_tags((string)($row[$key]??''));$data[]=$values;}
  if($format==='csv')return response()->streamDownload(function()use($data){$out=fopen('php://output','w');foreach($data as $row)fputcsv($out,array_map(fn($value)=>preg_match('/^[=+@-]/',$value)?"'".$value:$value,$row));fclose($out);},$filename.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
  if($format==='xlsx'){return response()->streamDownload(function()use($data){$book=new Spreadsheet();$sheet=$book->getActiveSheet();foreach($data as $r=>$row)foreach($row as $c=>$value)$sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($c+1).($r+1),$value,DataType::TYPE_STRING);$sheet->freezePane('A2');$sheet->getStyle('A1:K1')->getFont()->setBold(true);(new Xlsx($book))->save('php://output');$book->disconnectWorksheets();},$filename.'.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);}
  $html='<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:8px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:5px}th{background:#eee}</style></head><body><h1>PMS report</h1><table>';foreach($data as $r=>$row){$html.='<tr>';foreach($row as $value){$tag=$r===0?'th':'td';$html.='<'.$tag.'>'.htmlspecialchars($value,ENT_QUOTES,'UTF-8').'</'.$tag.'>';}$html.='</tr>';}$html.='</table></body></html>';
  $pdf=new Dompdf(['isRemoteEnabled'=>false]);$pdf->loadHtml($html);$pdf->setPaper('a4','landscape');$pdf->render();return response($pdf->output(),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$filename.'.pdf"']);
 }
}
