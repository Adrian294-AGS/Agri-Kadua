<?php

namespace App\Http\Controllers;

use App\Models\FarmerMember;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FarmerController extends Controller
{
    private const STATUSES = ['active', 'inactive', 'suspended'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $members = FarmerMember::query()
            ->with('crop')
            ->where('branch_id', $request->user()->branch_id)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . addcslashes($q, '%_\\') . '%';

                $query->where(function ($inner) use ($like) {
                    $inner->where('full_name', 'like', $like)
                        ->orWhere('member_code', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->orderBy('full_name')
            ->simplePaginate(15)
            ->withQueryString();

        return view('farmers.index', [
            'members' => $members,
            'q' => $q,
            'status' => $status,
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(Request $request, FarmerMember $member): View
    {
        abort_unless((int) $member->branch_id === (int) $request->user()->branch_id, 404);

        $member->load('crop', 'variety', 'coordinator');

        $receipts = $member->receipts()
            ->with('variety', 'payment')
            ->orderByDesc('delivery_date')
            ->orderByDesc('receipt_id')
            ->limit(10)
            ->get();

        return view('farmers.show', [
            'member' => $member,
            'receipts' => $receipts,
        ]);
    }
}