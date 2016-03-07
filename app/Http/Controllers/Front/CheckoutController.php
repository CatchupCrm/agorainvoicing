<?php

namespace App\Http\Controllers\Front;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Cart;
use App\Model\Product\Subscription;
use App\Model\Payment\Plan;
use App\Http\Controllers\Common\TemplateController;
use App\Model\Product\Product;
use App\Model\Product\Price;
use App\Http\Requests\Front\CheckoutRequest;
use App\User;
use App\Model\Common\Setting;
use App\Model\Common\Template;
use App\Model\Order\Order;
use App\Model\Product\Addon;
use App\Model\Order\Invoice;
use App\Model\Order\InvoiceItem;

class CheckoutController extends Controller {

    public $subscription;
    public $plan;
    public $templateController;
    public $product;
    public $price;
    public $user;
    public $setting;
    public $template;
    public $order;
    public $addon;
    public $invoice;
    public $invoiceItem;

    public function __construct() {
        $subscription = new Subscription();
        $this->subscription = $subscription;

        $plan = new Plan();
        $this->plan = $plan;

        $templateController = new TemplateController();
        $this->templateController = $templateController;

        $product = new Product();
        $this->product = $product;

        $price = new Price();
        $this->price = $price;

        $user = new User();
        $this->user = $user;

        $setting = new Setting();
        $this->setting = $setting;

        $template = new Template();
        $this->template = $template;

        $order = new Order();
        $this->order = $order;

        $invoice = new Invoice();
        $this->invoice = $invoice;

        $invoiceItem = new InvoiceItem();
        $this->invoiceItem = $invoiceItem;
    }

    public function CheckoutForm() {

        try {
            $content = Cart::getContent();
            return view('themes.default1.front.checkout', compact('content'));
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function postCheckout(CheckoutRequest $request) {

        try {
            if (\Cart::getSubTotal() > 0) {
                $v = $this->validate($request, [
                    'payment_gateway' => 'required'
                ]);
                
            }
            if (!$this->setting->where('id', 1)->first()) {
                return redirect()->back()->with('fails', 'Complete your settings');
            }

            $httpMethod = $request->method();
            //dd($httpMethod);
            if ($httpMethod == 'PATCH') {
                //do update the auth user
                
                \Auth::user()->fill($request->input())->save();
            } elseif ($httpMethod == 'POST') {
                //do saving user

                $str = str_random(8);
                $password = \Hash::make($str);
                $this->user->password = $password;
                $this->user->fill($request->input())->save();

                //send welcome note 
                $settings = $this->setting->where('id', 1)->first();
                $from = $settings->email;
                $to = $this->user->email;
                $data = $this->template->where('id', $settings->where('id', 1)->first()->welcome_mail)->first()->data;
                $replace = ['name' => $this->user->first_name . ' ' . $this->user->last_name, 'username' => $this->user->email, 'password' => $str];
                $this->templateController->Mailing($from, $to, $data, 'Welcome Email', $replace);

                \Auth::login($this->user);
            }

            /**
             * Do order, invoicing etc
             */
            $invoice_controller = new \App\Http\Controllers\Order\InvoiceController();
            $invoice = $invoice_controller->GenerateInvoice();
            $payment_method = $request->input('payment_gateway');
            $invoiceid = $invoice->id;
            $amount = $invoice->grand_total;
            $payment = $invoice_controller->doPayment($payment_method,$invoiceid,$amount);
            
            //trasfer the control to event if cart price is not equal 0
            if (Cart::getSubTotal() != 0) {
                //\Event::fire(new \App\Events\PaymentGateway(['request' => $request, 'cart' => Cart::getContent(), 'order' => []]));
            } else {
                
            }
        } catch (\Exception $ex) {
            //dd($ex);
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

//    public function GenerateOrder() {
//        try {
//
//            $products = [];
//            $items = \Cart::getContent();
//            foreach ($items as $item) {
//
//                //this is product
//                $id = $item->id;
//                $this->AddProductToOrder($id);
//            }
//        } catch (\Exception $ex) {
//            dd($ex);
//            throw new \Exception('Can not Generate Order');
//        }
//    }
//
//    public function AddProductToOrder($id) {
//        try {
//            $cart = \Cart::get($id);
//            $client = \Auth::user()->id;
//            $payment_method = \Input::get('payment_gateway');
//            $promotion_code = '';
//            $order_status = 'pending';
//            $serial_key = '';
//            $product = $id;
//            $addon = '';
//            $domain = '';
//            $price_override = $cart->getPriceSumWithConditions();
//            $qty = $cart->quantity;
//
//            $planid = $this->price->where('product_id', $id)->first()->subscription;
//
//            $or = $this->order->create(['client' => $client, 'payment_method' => $payment_method, 'promotion_code' => $promotion_code, 'order_status' => $order_status, 'serial_key' => $serial_key, 'product' => $product, 'addon' => $addon, 'domain' => $domain, 'price_override' => $price_override, 'qty' => $qty]);
//
//            $this->AddSubscription($or->id, $planid);
//        } catch (\Exception $ex) {
//            dd($ex);
//            throw new \Exception('Can not Generate Order for Product');
//        }
//    }
//
//    public function AddSubscription($orderid, $planid) {
//        try {
//            $days = $this->plan->where('id', $planid)->first()->days;
//            //dd($days);
//            if ($days > 0) {
//                $dt = \Carbon\Carbon::now();
//                //dd($dt);
//                $user_id = \Auth::user()->id;
//                $ends_at = $dt->addDays($days);
//            } else {
//                $ends_at = '';
//            }
//            $this->subscription->create(['user_id' => \Auth::user()->id, 'plan_id' => $planid, 'order_id' => $orderid, 'ends_at' => $ends_at]);
//        } catch (\Exception $ex) {
//            dd($ex);
//            throw new \Exception('Can not Generate Subscription');
//        }
//    }
//
//    public function GenerateInvoice() {
//        try {
//
//            $user_id = \Auth::user()->id;
//            $number = rand(11111111, 99999999);
//            $date = \Carbon\Carbon::now();
//            $grand_total = \Cart::getSubTotal();
//
//            $invoice = $this->invoice->create(['user_id' => $user_id, 'number' => $number, 'date' => $date, 'grand_total' => $grand_total]);
//            foreach (\Cart::getContent() as $cart) {
//                $this->CreateInvoiceItems($invoice->id, $cart);
//            }
//        } catch (\Exception $ex) {
//            dd($ex);
//            throw new \Exception('Can not Generate Invoice');
//        }
//    }
//
//    public function CreateInvoiceItems($invoiceid, $cart) {
//        try {
//
//            $product_name = $cart->name;
//            $regular_price = $cart->price;
//            $quantity = $cart->quantity;
//            $subtotal = $cart->getPriceSumWithConditions();
//
//            $tax_name = '';
//            $tax_percentage = '';
//
//            foreach ($cart->attributes['tax'] as $tax) {
//                //dd($tax['name']);
//                $tax_name .= $tax['name'] . ',';
//                $tax_percentage .= $tax['rate'] . ',';
//            }
//
//            //dd($tax_name);
//
//            $invoiceItem = $this->invoiceItem->create(['invoice_id' => $invoiceid, 'product_name' => $product_name, 'regular_price' => $regular_price, 'quantity' => $quantity, 'tax_name' => $tax_name, 'tax_percentage' => $tax_percentage, 'subtotal' => $subtotal]);
//        } catch (\Exception $ex) {
//            dd($ex);
//            throw new \Exception('Can not create Invoice Items');
//        }
//    }
}