<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_Hikashop
 * @subpackage 	trangell_Zarinpal
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die('Restricted access');

if (!class_exists ('checkHack')){
    require_once JPATH_SITE . '/plugins/hikashoppayment/zarinpal/trangell_inputcheck.php';
}

class plgHikashoppaymentZarinpal extends hikashopPaymentPlugin {
    var $accepted_currencies = array( "IRR" );
    var $multiple = true;
    var $name = 'zarinpal';
    var $pluginConfig = array(
        'merchant_id' => array("شناسه مرچنت",'input'),
        //'zaringate' => array('زرین گیت', 'boolean','0')
    );

    function __construct(&$subject, $config) {
        return parent::__construct($subject, $config);
    }

    function onBeforeOrderCreate(&$order,&$do){
        if(parent::onBeforeOrderCreate($order, $do) === true)
            return true;

        if (empty($this->payment_params->merchant_id)) {
            $this->app->enqueueMessage('لطفا تنظیمات پلاگین زرین پال را وارد نمایید','error');
            $do = false;
        }
    }

    function onAfterOrderConfirm(&$order,&$methods,$method_id) {
        parent::onAfterOrderConfirm($order,$methods,$method_id);
        $notify_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment='.$this->name.'&tmpl=component&lang='.$this->locale . $this->url_itemid.'&orderid='.$order->order_id;
        $return_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id='.$order->order_id . $this->url_itemid;
        $cancel_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id='.$order->order_id . $this->url_itemid;

        $config = JFactory::getConfig();
        $app	= JFactory::getApplication();
        $Amount =($order->cart->full_total->prices[0]->price_value_with_tax)/10; // Toman
        $Description = 'خرید محصول از فروشگاه   '. $config->get( 'sitename' );
        $Email = '';
        $Mobile = '';
        $CallbackURL =  $notify_url;

        try {
            ////////////////////////////////////////////////////////////////////////////////////////
            $data = array("merchant_id" =>$this->payment_params->merchant_id,
                "amount" => $Amount,
                "callback_url" => $CallbackURL,
                "description" => $Description,
                "metadata" => [ "email" => "0","mobile"=>"0"],
            );
            $jsonData = json_encode($data);
            $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ));

            $result = curl_exec($ch);
            $err = curl_error($ch);
            $result = json_decode($result, true, JSON_PRETTY_PRINT);
            curl_close($ch);
            /// ////////////////////////////////////////////////////////////////////////////////////
            /*		$client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
                    //$client = new SoapClient('https://sandbox.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']); // for local
                    $result = $client->PaymentRequest(
                        [
                        'MerchantID' => $this->payment_params->merchant_id,
                        'Amount' => $Amount,
                        'Description' => $Description,
                        'Email' => '',
                        'Mobile' => '',
                        'CallbackURL' => $CallbackURL,
                        ]
                    );*/


            //$resultStatus = abs($result->Status);

            if ($err) {
                echo "cURL Error #:" . $err;
            } else {
                if (empty($result['errors'])) {
                    if ($result['data']['code'] == 100) {

                        //header('Location: https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"]);
                        // $vars['zarinpal'] = 'https://www.zarinpal.com/pg/StartPay/'.$result['data']["authority"];
                        // $this->vars = $vars;
                        // return $this->showPage('end');
                        echo'<html><body>
<script type="text/javascript" src="https://cdn.zarinpal.com/zarinak/v1/checkout.js"></script>
<script type="text/javascript">
window.onload = function () {
Zarinak.setAuthority("' . $result['data']['authority'] . '");
Zarinak.showQR();
Zarinak.open();
};
</script>
</body></html>';
                    }else {

                        $msg= $this->getGateMsg('error');
                        $app	= JFactory::getApplication();
                        $link = $cancel_url;
                        $app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error');
                    }
                }
            }
            /*if ($resultStatus == 100) {
                //Header('Location: https://sandbox.zarinpal.com/pg/StartPay/'.$result->Authority); // local
                if ($this->payment_params->zaringate == 0){
                    $vars['zarinpal'] = 'https://www.zarinpal.com/pg/StartPay/'.$result->Authority;
                }
                else {
                    $vars['zarinpal'] = 'https://www.zarinpal.com/pg/StartPay/'.$result->Authority.'/ZarinGate';
                }
                $this->vars = $vars;
                return $this->showPage('end');
            }
             else {
                $msg= $this->getGateMsg('error');
                $app	= JFactory::getApplication();
                $link = $cancel_url;
                $app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error');
            }*/

        }
        catch(Exception $e) {
            $msg= $this->getGateMsg('error');
            $app	= JFactory::getApplication();
            $link = $cancel_url;
            $app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error');
        }
    }

    function onPaymentNotification(&$statuses)	{
        $app	= JFactory::getApplication();
        $jinput = $app->input;
        $orderId = $jinput->get->get('orderid', '0', 'INT');
        if($orderId != null){
            $Order = $this->getOrder($orderId);
            $this->loadPaymentParams($Order);
            // $mobile = $this->getInfo($Order->order_user_id);
            $return_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id='.$orderId.$this->url_itemid;
            $cancel_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id='.$orderId.$this->url_itemid;
            $history = new stdClass();
            $history->amount = round($Order->order_full_price,5)/10;
            //------------------------------------------------------

            $Authority = $jinput->get->get('Authority',  'STRING');
            $status = $jinput->get->get('Status', '', 'STRING');

            if (checkHack::checkString($status)){

                if ($status == 'OK') {

                    try {
                        ///////////////////////////////////////////////////////////////////////////////////////
                        ///
                        /// ////////////////////////////////////////////////////////////////////////////////////
                        //$client = new SoapClient('https://sandbox.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']); // for local
                        /*	$client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
                            $result = $client->PaymentVerification(
                                [
                                    'MerchantID' => $this->payment_params->merchant_id,
                                    'Authority' => $Authority,
                                    'Amount' => $history->amount,
                                ]
                            );*/
                        $data = array("merchant_id" => $this->payment_params->merchant_id, "authority" => $Authority, "amount" =>$history->amount);
                        $jsonData = json_encode($data);
                        $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
                        curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($jsonData)
                        ));
                        $result = curl_exec($ch);
                        curl_close($ch);
                        $result = json_decode($result, true);
                        //$resultStatus = abs($result->Status);
                        if ($result['data']['code'] == 100) {
                            // echo 'Transation success. RefID:' . $result['data']['ref_id'];
                            $msg= $this->getGateMsg($result['data']['code']);
                            $history->notified = 1;
                            $history->data = 'شماره پیگیری '. $result['data']['ref_id'];
                            $this->modifyOrder($orderId, 'confirmed', $history, true);
                            $app->redirect($return_url, '<h2>'.$msg.'</h2>'.'<h3>'. $result['data']['ref_id'] .'شماره پیگری ' .'</h3>' , $msgType='Message');

                        }
                        else {
                            $msg= $this->getGateMsg($result['errors']['code']);
                            $this->modifyOrder($orderId, 'cancelled', false, false);
                            $app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error');
                        }
                    }
                    catch(Exception $e) {
                        $msg= $this->getGateMsg('error');
                        $this->modifyOrder($orderId, 'cancelled', false, false);
                        $app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error');
                    }
                }
                else {
                    $msg= $this->getGateMsg(intval(17));
                    $this->modifyOrder($orderId, 'cancelled', false, false);
                    $app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error');
                }
            }
            else {
                $msg = $this->getGateMsg('hck2');
                $this->modifyOrder($orderId, 'cancelled', false, false);
                $app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error');
            }
        }
        else {
            $msg= $this->getGateMsg('notff');
            $this->modifyOrder($orderId, 'cancelled', false, false);
            $app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error');
        }
    }

    public function getGateMsg ($msgId) {
        switch($msgId){
            case	11: $out =  'شماره کارت نامعتبر است';break;
            case	12: $out =  'موجودي کافي نيست';break;
            case	13: $out =  'رمز نادرست است';break;
            case	14: $out =  'تعداد دفعات وارد کردن رمز بيش از حد مجاز است';break;
            case	15: $out =   'کارت نامعتبر است';break;
            case	17: $out =   'کاربر از انجام تراکنش منصرف شده است';break;
            case	18: $out =   'تاريخ انقضاي کارت گذشته است';break;
            case	21: $out =   'پذيرنده نامعتبر است';break;
            case	22: $out =   'ترمينال مجوز ارايه سرويس درخواستي را ندارد';break;
            case	23: $out =   'خطاي امنيتي رخ داده است';break;
            case	24: $out =   'اطلاعات کاربري پذيرنده نامعتبر است';break;
            case	25: $out =   'مبلغ نامعتبر است';break;
            case	31: $out =  'پاسخ نامعتبر است';break;
            case	32: $out =   'فرمت اطلاعات وارد شده صحيح نمي باشد';break;
            case	33: $out =   'حساب نامعتبر است';break;
            case	34: $out =   'خطاي سيستمي';break;
            case	35: $out =   'تاريخ نامعتبر است';break;
            case	41: $out =   'شماره درخواست تکراري است';break;
            case	42: $out =   'تراکنش Sale يافت نشد';break;
            case	43: $out =   'قبلا درخواست Verify داده شده است';break;
            case	44: $out =   'درخواست Verify يافت نشد';break;
            case	45: $out =   'تراکنش Settle شده است';break;
            case	46: $out =   'تراکنش Settle نشده است';break;
            case	47: $out =   'تراکنش Settle يافت نشد';break;
            case	48: $out =   'تراکنش Reverse شده است';break;
            case	49: $out =   'تراکنش Refund يافت نشد';break;
            case	51: $out =   'تراکنش تکراري است';break;
            case	52: $out =   'سرويس درخواستي موجود نمي باشد';break;
            case	54: $out =   'تراکنش مرجع موجود نيست';break;
            case	55: $out =   'تراکنش نامعتبر است';break;
            case	61: $out =   'خطا در واريز';break;
            case	100: $out =   'تراکنش با موفقيت انجام شد.';break;
            case	111: $out =   'صادر کننده کارت نامعتبر است';break;
            case	112: $out =   'خطاي سوئيچ صادر کننده کارت';break;
            case	113: $out =   'پاسخي از صادر کننده کارت دريافت نشد';break;
            case	114: $out =   'دارنده کارت مجاز به انجام اين تراکنش نيست';break;
            case	412: $out =   'شناسه قبض نادرست است';break;
            case	413: $out =   'شناسه پرداخت نادرست است';break;
            case	414: $out =   'سازمان صادر کننده قبض نامعتبر است';break;
            case	415: $out =   'زمان جلسه کاري به پايان رسيده است';break;
            case	416: $out =   'خطا در ثبت اطلاعات';break;
            case	417: $out =   'شناسه پرداخت کننده نامعتبر است';break;
            case	418: $out =   'اشکال در تعريف اطلاعات مشتري';break;
            case	419: $out =   'تعداد دفعات ورود اطلاعات از حد مجاز گذشته است';break;
            case	421: $out =   'IP نامعتبر است';break;
            case	500: $out =   'کاربر به صفحه زرین پال رفته ولي هنوز بر نگشته است';break;
            case	'1':
            case	'error': $out ='خطا غیر منتظره رخ داده است';break;
            case	'hck2': $out = 'لطفا از کاراکترهای مجاز استفاده کنید';break;
            case	'notff': $out = 'سفارش پیدا نشد';break;
            default: $out ='خطا غیر منتظره رخ داده است';break;
        }
        return $out;
    }

    public function getInfo ($id){
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('address_telephone');
        $query->from($db->qn('#__hikashop_address'));
        $query->where($db->qn('address_user_id') .  '=' . $db->q(intval($id)));
        $db->setQuery((string)$query);
        $result = $db->Loadresult();
        return $result;
    }
}
