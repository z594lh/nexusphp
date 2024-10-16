<?php
ini_set( 'display_errors', 'On' );
error_reporting(E_ALL);
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
$query = \App\Models\Medal::query();
$q = htmlspecialchars($_REQUEST['q'] ?? '');
if (!empty($q)) {
    $query->where('username', 'name', "%{$q}%");
}
$total = (clone $query)->count();
$perPage = 20;
list($paginationTop, $paginationBottom, $limit, $offset) = pager($perPage, $total, "?");
$rows = (clone $query)->offset($offset)->take($perPage)->orderBy('id', 'desc')->get();
$q = htmlspecialchars($q);
$title = nexus_trans('medal.label');
$columnImg = nexus_trans('medal.column.image_large');
$columnImageLargeLabel = nexus_trans('medal.column.description');
$columnImageLargeLabel = nexus_trans('medal.column.enablebuytime');
$columnImageLargeLabel = nexus_trans('medal.column.duration');
$columnImageLargeLabel = nexus_trans('medal.column.bonus');
$columnImageLargeLabel = nexus_trans('medal.column.price');
$columnImageLargeLabel = nexus_trans('medal.column.stock');
$columnImageLargeLabel = nexus_trans('medal.column.buy');
$columnImageLargeLabel = nexus_trans('medal.column.gift');
$header = '<h1 style="text-align: center">'. nexus_trans('medal.admin.list.page_title').'</h1>';
$filterForm = <<<FORM
<div>
    <form id="filterForm" action="{$_SERVER['REQUEST_URI']}" method="get">
        <input id="q" type="text" name="q" value="{$q}" placeholder="username">
        <input type="submit">
        <input type="reset" onclick="document.getElementById('q').value='';document.getElementById('filterForm').submit();">
    </form>
</div>
FORM;
stdhead($title);
begin_main_frame();
$table = '<table border="1" cellspacing="0" cellpadding="5" width="100%">
<thead>
<style>
   .colhead {
       width: 200px;
   }
</style>
<tr>
<td class="colhead">'.nexus_trans('medal.column.image_large').'</td>
<td class="colhead" style="width: 115px" >'.nexus_trans('medal.column.description').'</td>
<td class="colhead" style="width: 180px" >'.nexus_trans('medal.column.enablebuytime').'</td>
<td class="colhead">'.nexus_trans('medal.column.duration').'</td>
<td class="colhead">'.nexus_trans('medal.column.bonus').'</td>
<td class="colhead">'.nexus_trans('medal.column.price').'</td>
<td class="colhead">'.nexus_trans('medal.column.stock').'</td>
<td class="colhead">'.nexus_trans('medal.column.buy').'</td>
<td class="colhead">'.nexus_trans('medal.column.gift').'</td>
</tr>
</thead>';

$now = now();
$table .= '<tbody>';
$userMedals = \App\Models\UserMedal::query()->where('uid', $CURUSER['id'])
    ->orderBy('id', 'desc')
    ->get()
    ->keyBy('medal_id')
;
$bonusTaxpercentage = \App\Models\Setting::query()->where('id', 198)
    ->value('value');


foreach ($rows as $row) {

    $buyTxt = $giftTxt =  '';
    $buyClass = $giftClass =  'disabled';

    if($row->get_type == 2){
        $buyTxt = '仅授予';
    }else if ($userMedals->has($row->id)) {
        $buyTxt = '已拥有';
    }else if($CURUSER['seedbonus'] < $row->price){
        $buyTxt = '需要更多魔力';
    }else{
        $buyTxt = '购买';
        $buyClass = '';
    }

    if($row->get_type == 2){
        $giftTxt = '仅授予';
    }else if($CURUSER['seedbonus'] < ($row->price/(1-$bonusTaxpercentage/100)) ){
        $giftTxt = '需要更多魔力';
    }else{
        $giftTxt = '赠送';
        $giftClass = '';
    }


    $tr = '<tr>
                <td><img src="'.$row->image_large.'" style="max-width: 60px;max-height: 60px;" class="preview"  ></td>
                <td><h1>'.$row->name.'</h1>'.$row->description.'</td>
                <td>'.date('Y-m-d',strtotime($row->purchase_start)).'<br/>~<br/>'.date('Y-m-d',strtotime($row->purchase_end)).'</td>
                <td>'.($row->duration?$row->duration:"永久有效").'</td>
                <td>'.($row->bonus?$row->bonus."%":"无").'</td>
                <td>'.($row->price?number_format($row->price):number_format(1000000)).'</td>
                <td>'.($row->stock?$row->stock:0).'</td>
                <td><input type="button" '.$buyClass.' '.($buyClass=="disabled"?"":"class = 'buy' ").' data-id="'.$row->id.'" value="'.$buyTxt.'"></td>
                <td><input type="number" style="width: 60px" placeholder="UID"><input type="button"  '.$giftClass.' '.($giftClass=="disabled"?"":"class = 'gift' ").' data-id="'.$row->id.'"  value="'.$giftTxt.'" ></td>
          </tr>';

    $table.= $tr;//seedbonus

}
$table .= '</tbody></table>';
echo $header . $table . $paginationBottom;
end_main_frame();
$confirmBuyMsg = nexus_trans('medal.confirm_to_buy');
$confirmGiftMsg = nexus_trans('medal.confirm_to_gift');
$js = <<<JS
jQuery('.buy').on('click', function (e) {
    let medalId = jQuery(this).attr('data-id')
    layer.confirm("{$confirmBuyMsg}", function (index) {
        let params = {
            action: "buyMedal",
            params: {medal_id: medalId}
        }
        console.log(params)
        jQuery.post('ajax.php', params, function(response) {
            console.log(response)
            if (response.ret != 0) {
                layer.alert(response.msg)
                return
            }
            window.location.reload()
        }, 'json')
    })
})
jQuery('.gift').on('click', function (e) {
    let medalId = jQuery(this).attr('data-id')
    let uid = jQuery(this).prev().val()
    if (!uid) {
        layer.alert('Require UID')
        return
    }
    layer.confirm("{$confirmGiftMsg}" + uid + " ?", function (index) {
        let params = {
            action: "giftMedal",
            params: {medal_id: medalId, uid: uid}
        }
        console.log(params)
        jQuery.post('ajax.php', params, function(response) {
            console.log(response)
            if (response.ret != 0) {
                layer.alert(response.msg)
                return
            }
            window.location.reload()
        }, 'json')
    })
})
JS;
\Nexus\Nexus::js($js, 'footer', false);
stdfoot();
