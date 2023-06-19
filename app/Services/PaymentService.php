<?php

namespace App\Services;

use App\Models\Property;
use App\Models\LeaseAgreements;
use App\Models\PaymentType;
use App\Models\Payments;

use Carbon\Carbon;
use Elibyy\TCPDF\Facades\TCPDF;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PaymentService{

    public function save($request){
        $lease = LeaseAgreements::where('id', $request->get('lease_id'))->first();
        $paymentType = PaymentType::where('id', $request->get('payment_type_id'))->first();

        $payment_date =  Carbon::now();
        $uuid = Str::uuid(4)->toString();
        $shortUuid = substr(str_replace('-', '_', $uuid), 0, 12);
        
        $payment = new Payments([
            'lease_id' => $lease->id,
            'payment_type_id' => $paymentType->id,
            'payment_date' => $payment_date->format('Y-m-d'),
            'month_cancelled' => $request->get('month_cancelled'),
            'payment' => $lease->price,
            'user_creates' => auth()->user()->id,
            'uuid'=> $shortUuid,
           
        ]);

        try {
            $payment->save();
        } catch (\Throwable $th) {
            return $th;
        }
        
        return $payment;
    }

    public function history($lease){
        $payments = Payments::with(['paymentTypeId', 'leaseId.tenantId'])->where('lease_id', $lease->id)->orderBy('payment_date', 'DESC')->get();
        return $payments;
    }

    public function generatePDF($paymentId){
        $payment = Payments::with(['leaseId.tenantId', 'leaseId.propertyId.landlordId','paymentTypeId'])->where('id', $paymentId)->first();
        $logo_path = storage_path('img/header_logo_master.png');
        $qr_url= 'https://lofty-public.vercel.app/constancia-de-pago/' . $payment->uuid . '/detalles';
        $qr_raw = QrCode::size(640)->format('png')->generate($qr_url);
        $img = base64_encode($qr_raw);
        $html = View::make('pdf.payment', [
            'payment'=> $payment,
            'image' => $img
        ])->render();
        
        
        TCPDF::setMargins(14, 36, 14, true);
        TCPDF::AddPage();
        TCPDF::setImageScale(1);
        TCPDF::SetAutoPageBreak(false, 0);
        TCPDF::Image($logo_path, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        TCPDF::Image("@$qr_raw", 80, 170, 0, 50, '', '', '', false, 400, '', false, false, 0);
        TCPDF::writeHTML($html, true, false, true, false, '');

        $uuid = Str::uuid(4)->toString();

        return TCPDF::Output($uuid . ".pdf", 'I');
    }

}