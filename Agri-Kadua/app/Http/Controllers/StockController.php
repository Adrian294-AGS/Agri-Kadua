<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use App\Models\Terminal;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class StockController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request): View
    {
        $items = StockItem::query()
            ->with(['variety.crop', 'location'])
            ->where('branch_id', $request->user()->branch_id)
            ->orderBy('variety_id')
            ->orderBy('location_id')
            ->paginate(15)
            ->withQueryString();

        return view('stock.index', ['items' => $items]);
    }

    public function release(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'stock_id' => ['required', 'integer', 'exists:stock_items,stock_id'],
            'quantity_kg' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'stock_id.required' => 'Choose the stock item to release.',
            'stock_id.exists' => 'That stock item is no longer available.',
            'quantity_kg.required' => 'Enter the quantity to release.',
            'quantity_kg.numeric' => 'Quantity must be a number in kilograms.',
            'quantity_kg.gt' => 'Quantity must be greater than zero.',
            'reason.required' => 'Enter a reason for releasing the stock.',
        ]);

        $terminalId = Terminal::where('branch_id', $request->user()->branch_id)
            ->where('is_active', true)
            ->value('terminal_id');

        try {
            $release = $this->stock->release($data, $request->user(), $terminalId);
            app(\App\Services\NotificationService::class)->stockReleased($release, $request->user(), (int) $data['stock_id']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['quantity_kg' => $exception->getMessage()]);
        }

        return redirect()->route('stock.index')
            ->with('status', 'Released '.$release->quantity_kg.' kg ('.$release->milling_order_code.').');
    }
}
