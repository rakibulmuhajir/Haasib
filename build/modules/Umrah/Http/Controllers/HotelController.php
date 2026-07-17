<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\StoreHotelRequest;
use App\Modules\Umrah\Http\Requests\StoreHotelVendorRequest;
use App\Modules\Umrah\Http\Requests\UpdateHotelVendorRequest;
use App\Modules\Umrah\Http\Requests\UpdateMasterDataStatusRequest;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HotelController extends Controller
{
    public function __construct(private UmrahCoreService $service) {}

    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_SETTINGS_UPDATE), 403);
        $search = trim((string) $request->input('search', ''));
        $tab = $request->input('tab') === 'vendors' ? 'vendors' : 'hotels';

        return Inertia::render('Umrah/Settings/Hotels', [
            'company' => ['id' => $company->id, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'hotels' => Hotel::where('company_id', $company->id)
                ->with(['vendor:id,name,logo_url,is_active', 'roomRates' => fn ($query) => $query->where('is_active', true)])
                ->when($search !== '', fn ($query) => $query->where(fn ($hotel) => $hotel
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('city', 'ilike', "%{$search}%")
                    ->orWhereHas('vendor', fn ($vendor) => $vendor->where('name', 'ilike', "%{$search}%"))))
                ->orderBy('city')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'hotelVendors' => HotelVendor::where('company_id', $company->id)
                ->withCount(['hotels' => fn ($query) => $query->where('is_active', true)])
                ->when($search !== '', fn ($query) => $query->where(fn ($vendor) => $vendor
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('vendor_number', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('city', 'ilike', "%{$search}%")))
                ->orderBy('name')
                ->paginate(20, ['*'], 'vendor_page')
                ->withQueryString(),
            'filters' => ['search' => $search, 'tab' => $tab],
            'roomTypes' => HotelRoomRate::TYPES,
        ]);
    }

    public function create(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_SETTINGS_UPDATE), 403);

        return Inertia::render('Umrah/Settings/HotelCreate', [
            'company' => ['id' => $company->id, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'vendors' => HotelVendor::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(),
            'roomTypes' => HotelRoomRate::TYPES,
            'editingHotel' => null,
        ]);
    }

    public function edit(Request $request, string $companySlug, string $hotel): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_SETTINGS_UPDATE), 403);
        $record = Hotel::where('company_id', $company->id)->with('roomRates')->findOrFail($hotel);

        return Inertia::render('Umrah/Settings/HotelCreate', [
            'company' => ['id' => $company->id, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'vendors' => HotelVendor::where('company_id', $company->id)
                ->where(fn ($query) => $query->where('is_active', true)->orWhereKey($record->hotel_vendor_id))
                ->orderBy('name')->get(),
            'roomTypes' => HotelRoomRate::TYPES,
            'editingHotel' => $record,
        ]);
    }

    public function createVendor(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_SETTINGS_UPDATE), 403);

        return Inertia::render('Umrah/Settings/HotelVendorCreate', [
            'company' => ['id' => $company->id, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'nextVendorNumber' => $this->service->nextHotelVendorNumber($company->id),
            'editingVendor' => null,
        ]);
    }

    public function editVendor(Request $request, string $companySlug, string $hotelVendor): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_SETTINGS_UPDATE), 403);

        return Inertia::render('Umrah/Settings/HotelVendorCreate', [
            'company' => ['id' => $company->id, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'nextVendorNumber' => null,
            'editingVendor' => HotelVendor::where('company_id', $company->id)->findOrFail($hotelVendor),
        ]);
    }

    public function storeVendor(StoreHotelVendorRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();
        HotelVendor::create([...$data, 'company_id' => $company->id, 'vendor_number' => $data['vendor_number'] ?: $this->service->nextHotelVendorNumber($company->id), 'is_active' => true]);

        return redirect()->route('umrah.hotels.index', ['company' => $company->slug])
            ->with('success', 'Hotel vendor added successfully.');
    }

    public function store(StoreHotelRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();
        DB::transaction(function () use ($company, $data) {
            $hotel = Hotel::create(['company_id' => $company->id, 'hotel_vendor_id' => $data['hotel_vendor_id'] ?? null, 'name' => $data['name'], 'city' => $data['city'], 'notes' => $data['notes'] ?? null, 'is_active' => true]);
            foreach ($data['room_rates'] as $rate) {
                HotelRoomRate::create(['company_id' => $company->id, 'hotel_id' => $hotel->id, 'room_type' => $rate['room_type'], 'retail_amount' => round((float) $rate['retail_amount'], 2), 'cost_amount' => round((float) $rate['cost_amount'], 2), 'is_active' => true]);
            }
        });

        return redirect()->route('umrah.hotels.index', ['company' => $company->slug])
            ->with('success', 'Hotel added successfully.');
    }

    public function update(StoreHotelRequest $request, string $companySlug, string $hotel): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();
        DB::transaction(function () use ($company, $data, $hotel): void {
            $record = Hotel::where('company_id', $company->id)->lockForUpdate()->findOrFail($hotel);
            $record->update([
                'hotel_vendor_id' => $data['hotel_vendor_id'],
                'name' => $data['name'],
                'city' => $data['city'],
                'notes' => $data['notes'] ?? null,
            ]);

            $submittedTypes = collect($data['room_rates'])->pluck('room_type');
            $record->roomRates()->whereNotIn('room_type', $submittedTypes)->update(['is_active' => false]);
            foreach ($data['room_rates'] as $rate) {
                $roomRate = HotelRoomRate::withTrashed()->firstOrNew([
                    'company_id' => $company->id,
                    'hotel_id' => $record->id,
                    'room_type' => $rate['room_type'],
                ]);
                $roomRate->fill([
                    'retail_amount' => round((float) $rate['retail_amount'], 2),
                    'cost_amount' => round((float) $rate['cost_amount'], 2),
                    'is_active' => true,
                ]);
                $roomRate->deleted_at = null;
                $roomRate->save();
            }
        });

        return redirect()->route('umrah.hotels.index', ['company' => $company->slug])
            ->with('success', 'Hotel updated successfully. Existing vouchers keep their original rate snapshots.');
    }

    public function updateVendor(UpdateHotelVendorRequest $request, string $companySlug, string $hotelVendor): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = HotelVendor::where('company_id', $company->id)->findOrFail($hotelVendor);
        $record->update($request->validated());

        return redirect()->route('umrah.hotels.index', ['company' => $company->slug, 'tab' => 'vendors'])
            ->with('success', 'Hotel vendor updated successfully.');
    }

    public function updateStatus(UpdateMasterDataStatusRequest $request, string $companySlug, string $hotel): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Hotel::where('company_id', $company->id)->with('vendor')->findOrFail($hotel);
        $active = (bool) $request->validated('is_active');
        if ($active && ! $record->vendor?->is_active) {
            throw ValidationException::withMessages(['hotel' => 'Reactivate the hotel vendor before reactivating this hotel.']);
        }
        $record->update(['is_active' => $active]);

        return back()->with('success', $active ? 'Hotel reactivated successfully.' : 'Hotel deactivated successfully. Existing vouchers are unchanged.');
    }

    public function updateVendorStatus(UpdateMasterDataStatusRequest $request, string $companySlug, string $hotelVendor): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = HotelVendor::where('company_id', $company->id)->findOrFail($hotelVendor);
        $active = (bool) $request->validated('is_active');
        if (! $active && $record->hotels()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['vendor' => 'Deactivate or reassign this vendor\'s active hotels first.']);
        }
        $record->update(['is_active' => $active]);

        return back()->with('success', $active ? 'Hotel vendor reactivated successfully.' : 'Hotel vendor deactivated successfully.');
    }
}
