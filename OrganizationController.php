<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreOrganizationRequest;
use App\Http\Requests\Organizations\UpdateOrganizationRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Models\Favourite;
use App\Models\Organization;
use App\Models\Rating;
use App\Services\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrganizationController extends Controller
{
    protected $organizationService;

    public function __construct(OrganizationService $organizationService)
    {
        $this->organizationService = $organizationService;
    }
    public function index(Request $request)
    {
        $search = $request->input('search');
        $organizations = $this->organizationService->getAllOrganizations($search);
        return $this->formatResponse($organizations, 'Organizations retrieved successfully.');
    }

    // Store organization
    public function store(StoreOrganizationRequest $request)
    {
        $data = $request->validated();
        $organization = $this->organizationService->createOrganization($data);
        return $this->formatResponse($organization, 'organization created successfully.');
    }

    // Update organization
    public function update(UpdateOrganizationRequest $request, $id)
    {
        if (!$this->organizationService->organizationExists($id)) {
            return $this->formatResponse('organization not found.',false,404);
           
        }

        $data = $request->validated();
        $organization = $this->organizationService->getOrganizationById($id);
        $updatedOrganization = $this->organizationService->updateOrganization($organization, $data);
        return $this->formatResponse($updatedOrganization, 'organization updated successfully.');
    }

    // Show organization

    public function show($id)
    {
        if (!$this->organizationService->organizationExists($id)) {
            return $this->formatResponse('Organization not found.', false, 404);
        }
    
        $organization = $this->organizationService->getOrganizationById($id);
    
        if (Auth::check()) {
            $userId = Auth::id();
            $isFav = $organization->favourite->where('user_id', $userId)->isNotEmpty();
        } else {
            $isFav = false;
        }
    
        $organization->setRelation('favourite', collect());
    
        $totalRatings = $organization->ratings->count();
        $averageRating = $totalRatings > 0 ? round($organization->ratings->avg('rating'), 3) : 0;
    
        $coupons = Coupon::where('organization_id', $id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    
        return response()->json([
            'status' => true,
            'message' => 'Organization retrieved successfully.',
            'data' => [
                'organization' => $organization,
                'averageRating' => $averageRating,
                'isFav' => $isFav,
                'coupons' => CouponResource::collection($coupons),
            ],
        ]);
    }
    
    // Delete organization
    public function destroy($id)
    {
        if (!$this->organizationService->organizationExists($id)) {
            return $this->formatResponse('organization not found.',false,404);
        }

        $organization = $this->organizationService->getOrganizationById($id);
        $this->organizationService->deleteOrganization($organization);
        return $this->formatResponse('organization deleted successfully.');
    }

    public function toggleFavourite($id)
    {
        $userId = Auth::id();

        if (!$this->organizationService->organizationExists($id)) {
            return $this->formatResponse('organization not found.',false,404);
        }

        $favourite = Favourite::where('user_id', $userId)
            ->where('organization_id', $id)
            ->first();

        if ($favourite) {
            $favourite->delete();

            return response()->json([
                'data'=>[],
                'status' => true,
                'message' => __('messages.organization_removed'),
                // 'is_favourite' => false
            ]);
        } else {
            Favourite::create([
                'user_id' => $userId,
                'organization_id' => $id
            ]);
            

            return response()->json([
                'data'=>[],
                'status' => true,
                'message' => __('messages.organization_added'),
                // 'is_favourite' => true
            ]);
        }
    }

    public function rateOrganization(Request $request, $organizationId)
    {
        $request->validate([
            'rating' => 'required|numeric|min:1|max:5',
        ]);
        $userId = Auth::id();

        $existingRating = Rating::where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->first();

        if ($existingRating) {
            $existingRating->rating = $request->rating;
            $existingRating->save();

            return response()->json([
                'status' => true,
                'message' => 'Rating updated successfully.',
                'data' => $existingRating
            ]);
        } else {
            $rating = Rating::create([
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'rating' => $request->rating
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Rating added successfully.',
                'data' => $rating
            ]);
        }
    }
}
