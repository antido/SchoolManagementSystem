<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The audit trail, read-only, for people with audit.view (administrators).
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('audit.view'), 403);

        $filters = [
            'search' => trim((string) $request->query('search')),
            'area' => (string) $request->query('area'),
            'from' => (string) $request->query('from'),
            'to' => (string) $request->query('to'),
        ];

        $date = fn (string $value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? Carbon::parse($value) : null;

        return Inertia::render('Audit/Index', [
            'entries' => AuditLog::query()
                ->with('user:id,name')
                ->when($filters['search'] !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereContains('description', $filters['search'])
                    ->orWhereHas('user', fn ($query) => $query->whereContains('name', $filters['search']))))
                // Areas are plain names like "award_given"; "_" is escaped so it is not a wildcard.
                ->when(preg_match('/^[a-z_]+$/', $filters['area']), fn ($query) => $query->whereRaw(
                    "action like ? escape '!'",
                    [str_replace('_', '!_', $filters['area']).'.%'],
                ))
                ->when($date($filters['from']), fn ($query, $from) => $query->where('created_at', '>=', $from->startOfDay()))
                ->when($date($filters['to']), fn ($query, $to) => $query->where('created_at', '<=', $to->endOfDay()))
                ->latest('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (AuditLog $entry) => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'description' => $entry->description,
                    'actor' => $entry->user?->name,
                    'old_values' => $entry->old_values,
                    'new_values' => $entry->new_values,
                    'ip_address' => $entry->ip_address,
                    'created_at' => $entry->created_at->toIso8601String(),
                ]),
            'filters' => $filters,
            // The kinds of record in the trail, e.g. "student" from "student.updated".
            'areas' => AuditLog::query()->distinct()->orderBy('action')->pluck('action')
                ->map(fn (string $action) => explode('.', $action)[0])
                ->unique()
                ->values()
                ->map(fn (string $area) => ['value' => $area, 'label' => ucfirst(str_replace('_', ' ', $area))]),
        ]);
    }
}
