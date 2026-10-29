<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\PaymentScreenshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function uploadScreenshot(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'screenshot' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'transaction_id' => 'nullable|string',
            'amount' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $path = $request->file('screenshot')->store('payment_screenshots', 'public');

        $payment = PaymentScreenshot::create([
            'user_id' => $user->id,
            'image' => $path,
            'transaction_id' => $request->transaction_id,
            'amount' => $request->amount,
            'status' => 'pending',
        ]);

        return response()->json(['success' => true, 'message' => 'Payment screenshot uploaded successfully', 'data' => $payment], 201);
    }

    public function myPayments(Request $request)
    {
        $payments = PaymentScreenshot::where('user_id', $request->user()->id)->latest()->paginate($request->get('per_page', 20));
        return response()->json(['success' => true, 'data' => $payments]);
    }

    public function index(Request $request)
    {
        $payments = PaymentScreenshot::with('user')->latest()->paginate($request->get('per_page', 20));
        return response()->json(['success' => true, 'data' => $payments]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $payment = PaymentScreenshot::find($id);
        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        $payment->status = $request->status;
        $payment->save();

        return response()->json(['success' => true, 'message' => 'Status updated successfully', 'data' => $payment]);
    }
}

