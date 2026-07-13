<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\StoreHotelRequest;
use App\Modules\Umrah\Http\Requests\StoreHotelVendorRequest;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return Inertia::render('Umrah/Settings/Hotels', [
            'company' => ['id' => $company->id, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'hotels' => Hotel::where('company_id', $company->id)
                ->with(['vendor:id,name,logo_url', 'roomRates' => fn ($query) => $query->where('is_active', true)])
                ->when($search !== '', fn ($query) => $query->where(fn ($hotel) => $hotel
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('city', 'ilike', "%{$search}%")
                    ->orWhereHas('vendor', fn ($vendor) => $vendor->where('name', 'ilike', "%{$search}%"))))
                ->orderBy('city')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['search' => $search],
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
        ]);
    }

    public function createVendor(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_SETTINGS_UPDATE), 403);

        return Inertia::render('Umrah/Settings/HotelVendorCreate', [
            'company' => ['id' => $company->id, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'nextVendorNumber' => $this->service->nextHotelVendorNumber($company->id),
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
}
