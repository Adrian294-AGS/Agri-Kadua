<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('user:user_id,full_name,branch_id')
            ->whereHas('user', fn ($query) => $query->where('branch_id', $request->user()->branch_id))
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->query('module')))
            ->orderByDesc('changed_at')
            ->orderByDesc('audit_id')
            ->paginate(25)
            ->withQueryString();

        return view('audit.index', ['logs' => $logs]);
    }
}
