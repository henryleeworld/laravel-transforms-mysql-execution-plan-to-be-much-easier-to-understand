<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertySearchResource;
use App\Models\Geoobject;
use App\Models\Property;
use Illuminate\Http\Request;

class PropertySearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $propertiesQuery = Property::query()
            ->with([
                'city',
                'apartments.apartment_type',
                'apartments.beds.bed_type',
                'apartments.prices' => function($query) use ($request) {
                    $query->validForRange([
                        $request->start_date ?? now()->addDay()->toDateString(),
                        $request->end_date ?? now()->addDays(2)->toDateString(),
                    ]);
                },
            ])
            ->when($request->city, function($query) use ($request) {
                $query->where('city_id', $request->city);
            })
            ->when($request->country, function($query) use ($request) {
                $query->whereHas('city', fn($q) => $q->where('country_id', $request->country));
            })
            ->when($request->geoobject, function($query) use ($request) {
                $geoobject = Geoobject::find($request->geoobject);
                if ($geoobject) {
                    $condition = "(
                        6371 * acos(
                            cos(radians(" . $geoobject->lat . "))
                            * cos(radians(`lat`))
                            * cos(radians(`long`) - radians(" . $geoobject->long . "))
                            + sin(radians(" . $geoobject->lat . ")) * sin(radians(`lat`))
                        ) < 10
                    )";
                    $query->whereRaw($condition);
                }
            })
            ->when($request->adults && $request->children, function($query) use ($request) {
                $query->withWhereHas('apartments', function($query) use ($request) {
                    $query->where('capacity_adults', '>=', $request->adults)
                        ->where('capacity_children', '>=', $request->children)
                        ->when($request->start_date && $request->end_date, function($query) use ($request) {
                            $query->whereDoesntHave('bookings', function($q) use ($request) {
                                $q->validForRange([$request->start_date, $request->end_date]);
                            });
                        })
                        ->orderBy('capacity_adults')
                        ->orderBy('capacity_children')
                        ->take(1);
                });
            })
            ->when($request->price_from, function($query) use ($request) {
                $query->whereHas('apartments.prices', function($query) use ($request) {
                    $query->where('price', '>=', $request->price_from);
                });
            })
            ->when($request->price_to, function($query) use ($request) {
                $query->whereHas('apartments.prices', function($query) use ($request) {
                    $query->where('price', '<=', $request->price_to);
                });
            });

        $properties = $propertiesQuery
            ->orderBy('bookings_avg_rating', 'desc')->dumpExplainForHumans()
            ->paginate(10)
            ->withQueryString();

        return [
            'properties' => PropertySearchResource::collection($properties)
                ->response()
                ->getData(true),
        ];
    }
}
