<?php
/**
* Text Translate
**/
global $lang;
$lang = get_lang();
function get_lang()
{
	$data = array (
		'due_date' => 'Ngày đáo hạn [Due Date]',
		'amount'   => 'Số tiền [Amount]',
		'enabled_title' => 'Kích hoạt/Tắt [Enable/Disable]',
		'enabled_label' => 'Kích hoạt LIT Gateway [Enable LIT Gateway]',
		'enabled_description'	=> '',
		'title_title'	=>	'Tiêu đề [Title]',
		'title_description' => 'Điều này kiểm soát tiêu đề mà người dùng nhìn thấy trong quá trình thanh toán. [This controls the title which the user sees during checkout.]',
		'description_title' => 'Mô tả [Description]',
		'description_description' => 'Điều này kiểm soát mô tả mà người dùng nhìn thấy trong quá trình thanh toán. [This controls the title which the user sees during checkout.]',
		'merchant_key_title'	=> 'Live Merchant Key',
		'private_key_title'	 => 'Live Private Key',
		'method_description' => 'LIT Gateway payment cho phép người mua mua sản phẩm của bạn theo nhiều đợt [LIT Gateway payment allow your buyers to buy your product in several instalment]',
		'currency_not_support' =>'Đơn vị tiền tệ hiện tại không được LIT hỗ trợ [The current currency is not supported by LIT]',
		'try_again'	=> 'Vui lòng thử lại [Please Try Again]',
		'connection_error' =>'Kết nối thất bại [Connection Error]',
		'the_total'	=> 'Tổng số phải từ [The total have to be between]',
		'and'	=>'và [and]',
		'to_use' =>'để sử dụng LIT [to use LIT]',
		'proceed'	=> 'Thực hiện với LIT [Proceed with LIT]',
		'unable'=>'Không thể thực hiện [Unable to process]',

	);
	return $data;
}
