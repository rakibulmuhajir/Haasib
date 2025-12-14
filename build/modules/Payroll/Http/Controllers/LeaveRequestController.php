<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Payroll\Http\Requests\StoreLeaveRequestRequest;
use Modules\Payroll\Http\Requests\UpdateLeaveRequestRequest;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\LeaveRequest;
use Modules\Payroll\Models\LeaveType;

class LeaveRequestController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $leaveRequests = LeaveRequest::where('company_id', $company->id)
            ->with(['employee:id,first_name,last_name,employee_number', 'leaveType:id,code,name'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Payroll/LeaveRequests/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'leaveRequests' => $leaveRequests,
            'filters' => request()->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $employees = Employee::where('company_id', $company->id)
            ->where('is_active', true)
            ->select('id', 'first_name', 'last_name', 'employee_number')
            ->orderBy('last_name')
            ->get();

        $leaveTypes = LeaveType::where('company_id', $company->id)
            ->where('is_active', true)
            ->select('id', 'code', 'name', 'is_paid')
            ->orderBy('code')
            ->get();

        return Inertia::render('Payroll/LeaveRequests/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
        ]);
    }

    public function store(StoreLeaveRequestRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $leaveRequest = LeaveRequest::create([
            ...$request->validated(),
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('leave-requests.index', ['company' => $company->slug])
            ->with('success', 'Leave request created successfully.');
    }

    public function show(string $companySlug, string $leaveRequestId): Response
    {
        $company = app(CurrentCompany::class)->get();

        $leaveRequest = LeaveRequest::where('company_id', $company->id)
            ->with(['employee:id,first_name,last_name,employee_number', 'leaveType:id,code,name', 'approvedBy:id,name'])
            ->findOrFail($leaveRequestId);

        return Inertia::render('Payroll/LeaveRequests/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'leaveRequest' => $leaveRequest,
        ]);
    }

    public function edit(string $companySlug, string $leaveRequestId): Response
    {
        $company = app(CurrentCompany::class)->get();

        $leaveRequest = LeaveRequest::where('company_id', $company->id)->findOrFail($leaveRequestId);

        if ($leaveRequest->status !== 'pending') {
            return redirect()
                ->route('leave-requests.show', ['company' => $company->slug, 'leaveRequest' => $leaveRequest->id])
                ->with('error', 'Only pending requests can be edited.');
        }

        $employees = Employee::where('company_id', $company->id)
            ->where('is_active', true)
            ->select('id', 'first_name', 'last_name', 'employee_number')
            ->orderBy('last_name')
            ->get();

        $leaveTypes = LeaveType::where('company_id', $company->id)
            ->where('is_active', true)
            ->select('id', 'code', 'name', 'is_paid')
            ->orderBy('code')
            ->get();

        return Inertia::render('Payroll/LeaveRequests/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'leaveRequest' => $leaveRequest,
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
        ]);
    }

    public function update(UpdateLeaveRequestRequest $request, string $companySlug, string $leaveRequestId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $leaveRequest = LeaveRequest::where('company_id', $company->id)->findOrFail($leaveRequestId);

        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', 'Only pending requests can be edited.');
        }

        $leaveRequest->update($request->validated());

        return redirect()
            ->route('leave-requests.show', ['company' => $company->slug, 'leaveRequest' => $leaveRequest->id])
            ->with('success', 'Leave request updated successfully.');
    }

    public function approve(string $companySlug, string $leaveRequestId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $leaveRequest = LeaveRequest::where('company_id', $company->id)->findOrFail($leaveRequestId);

        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', 'Only pending requests can be approved.');
        }

        $leaveRequest->update([
            'status' => 'approved',
            'approved_by_user_id' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Leave request approved.');
    }

    public function reject(string $companySlug, string $leaveRequestId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $leaveRequest = LeaveRequest::where('company_id', $company->id)->findOrFail($leaveRequestId);

        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', 'Only pending requests can be rejected.');
        }

        $leaveRequest->update([
            'status' => 'rejected',
            'approved_by_user_id' => auth()->id(),
            'rejection_reason' => request('rejection_reason'),
        ]);

        return back()->with('success', 'Leave request rejected.');
    }

    public function destroy(string $companySlug, string $leaveRequestId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $leaveRequest = LeaveRequest::where('company_id', $company->id)->findOrFail($leaveRequestId);

        if (!in_array($leaveRequest->status, ['pending', 'cancelled'])) {
            return back()->with('error', 'Cannot delete non-pending requests.');
        }

        $leaveRequest->delete();

        return redirect()
            ->route('leave-requests.index', ['company' => $company->slug])
            ->with('success', 'Leave request deleted successfully.');
    }
}
