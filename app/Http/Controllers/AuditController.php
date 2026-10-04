<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Audit\AuditEventData;
use App\Modules\Audit\CompareAudit;
use App\Modules\Audit\ListAuditEvents;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        $request->validate(['page' => 'nullable|integer|min:1']);

        return response()->json(app(ListAuditEvents::class)->read(AccessContext::forRequest($request), $trip));
    }

    public function show(Request $request, Trip $trip, AuditEvent $auditEvent, CompareAudit $compare)
    {
        $this->authorize('view', $trip);
        $request->validate(['page' => 'nullable|integer|min:1']);
        $rows = $compare->build($auditEvent->before, $auditEvent->after);
        $page = $request->integer('page', 1);

        return response()->json(['id' => $auditEvent->id, 'revision' => $auditEvent->revision, 'reason' => $auditEvent->reason, 'summary' => AuditEventData::states($auditEvent->summary),
            'data' => array_slice($rows, ($page - 1) * 20, 20), 'total' => count($rows), 'current_page' => $page,
            'last_page' => max(1, (int) ceil(count($rows) / 20))]);
    }
}
