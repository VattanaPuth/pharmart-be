<?php

namespace App\Http\Controllers\CustomerControllers;

use App\Http\Controllers\Controller;
use App\Services\CustomerServices\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class InvoiceController extends Controller
{
	public function __construct(private InvoiceService $invoiceService) {}

	private function customerId(Request $request): ?int
	{
		return $request->user()?->customer?->id;
	}

	public function generate(Request $request, int $orderId): JsonResponse
	{
		$customerId = $this->customerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		try {
			$invoice = $this->invoiceService->generateInvoiceForCustomer($customerId, $orderId);
		} catch (RuntimeException $e) {
			return response()->json(['message' => $e->getMessage()], 422);
		}

		return response()->json([
			'message' => 'Invoice generated successfully',
			'data'    => $invoice,
		], 201);
	}

	public function index(Request $request): JsonResponse
	{
		$customerId = $this->customerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$validated = $request->validate([
			'invoice_number'    => 'nullable|string|max:50',
			'order_number'      => 'nullable|string|max:50',
			'invoice_date_from' => 'nullable|date',
			'invoice_date_to'   => 'nullable|date',
			'delivered_method'  => 'nullable|in:pickup,delivery',
		]);

		$invoices = $this->invoiceService->getInvoices($validated, $customerId);

		return response()->json([
			'message' => 'Invoices retrieved successfully',
			'data'    => $invoices,
		]);
	}

	public function show(Request $request, int $invoiceId): JsonResponse
	{
		$customerId = $this->customerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$invoice = $this->invoiceService->getInvoiceById($invoiceId, $customerId);

		if (!$invoice) {
			return response()->json(['message' => 'Invoice not found'], 404);
		}

		return response()->json([
			'message' => 'Invoice retrieved successfully',
			'data'    => $invoice,
		]);
	}

	public function print(Request $request, int $invoiceId): JsonResponse
	{
		$customerId = $this->customerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$invoice = $this->invoiceService->getPrintableInvoice($invoiceId, $customerId);

		if (!$invoice) {
			return response()->json(['message' => 'Invoice not found'], 404);
		}

		return response()->json([
			'message' => 'Printable invoice retrieved successfully',
			'data'    => $invoice,
		]);
	}

}
