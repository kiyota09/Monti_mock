<?php

namespace App\Services\Logistics;

use Illuminate\Support\Facades\Http;

/**
 * Orders multi-client drop-off stops by nearest-first (greedy
 * nearest-neighbour from the Monti origin) and resolves the real road
 * geometry for the whole ordered trip via OSRM.
 */
class RouteOptimizerService
{
    /**
     * Order stops so the closest unvisited stop is always visited next.
     *
     * @param  array{lat:float,lng:float}  $origin
     * @param  array<int,array<string,mixed>>  $stops  each requires latitude/longitude
     * @return array<int,array<string,mixed>>
     */
    public function orderByNearest(array $origin, array $stops): array
    {
        $remaining = array_values(array_filter($stops, fn ($s) => $s['latitude'] !== null && $s['longitude'] !== null));
        $skipped   = array_values(array_filter($stops, fn ($s) => $s['latitude'] === null || $s['longitude'] === null));

        $ordered = [];
        $cursorLat = (float) $origin['lat'];
        $cursorLng = (float) $origin['lng'];

        while (! empty($remaining)) {
            $nearestIdx = null;
            $nearestKm  = null;

            foreach ($remaining as $idx => $stop) {
                $km = $this->haversine($cursorLat, $cursorLng, (float) $stop['latitude'], (float) $stop['longitude']);
                if ($nearestKm === null || $km < $nearestKm) {
                    $nearestKm  = $km;
                    $nearestIdx = $idx;
                }
            }

            $chosen = $remaining[$nearestIdx];
            $chosen['leg_km'] = round((float) $nearestKm, 2);
            $ordered[] = $chosen;

            $cursorLat = (float) $chosen['latitude'];
            $cursorLng = (float) $chosen['longitude'];

            unset($remaining[$nearestIdx]);
            $remaining = array_values($remaining);
        }

        // Stops without coordinates keep their original order at the end.
        foreach ($skipped as $stop) {
            $stop['leg_km'] = null;
            $ordered[] = $stop;
        }

        return $ordered;
    }

    /**
     * Total straight-line length of an ordered origin->stops trip (km).
     */
    public function straightLineDistance(array $origin, array $orderedStops): float
    {
        $total = 0.0;
        $lat = (float) $origin['lat'];
        $lng = (float) $origin['lng'];

        foreach ($orderedStops as $stop) {
            if ($stop['latitude'] === null || $stop['longitude'] === null) {
                continue;
            }
            $total += $this->haversine($lat, $lng, (float) $stop['latitude'], (float) $stop['longitude']);
            $lat = (float) $stop['latitude'];
            $lng = (float) $stop['longitude'];
        }

        return round($total, 2);
    }

    /**
     * Resolve real road geometry for an ordered trip via OSRM.
     *
     * @param  array{lat:float,lng:float}  $origin
     * @param  array<int,array<string,mixed>>  $orderedStops
     * @return array{geometry:array,distance_km:float,estimated_minutes:int}|null
     */
    public function resolveGeometry(array $origin, array $orderedStops): ?array
    {
        $points = [[(float) $origin['lat'], (float) $origin['lng']]];

        foreach ($orderedStops as $stop) {
            if ($stop['latitude'] === null || $stop['longitude'] === null) {
                continue;
            }
            $points[] = [(float) $stop['latitude'], (float) $stop['longitude']];
        }

        if (count($points) < 2) {
            return null;
        }

        try {
            // OSRM expects lng,lat;lng,lat;...
            $coordString = implode(';', array_map(fn ($p) => sprintf('%s,%s', $p[1], $p[0]), $points));

            $url = sprintf(
                'https://router.project-osrm.org/route/v1/driving/%s?overview=full&geometries=geojson',
                $coordString
            );

            $response = Http::timeout(20)->get($url);

            if (! $response->ok()) {
                return null;
            }

            $payload = $response->json();
            $route = $payload['routes'][0] ?? null;

            if (empty($route['geometry']['coordinates'])) {
                return null;
            }

            // OSRM returns [lng, lat]; flip to [lat, lng] for Leaflet.
            $geometry = array_map(
                fn ($c) => [(float) $c[1], (float) $c[0]],
                $route['geometry']['coordinates']
            );

            return [
                'geometry'          => $geometry,
                'distance_km'       => round($route['distance'] / 1000, 2),
                'estimated_minutes' => (int) ceil($route['duration'] / 60),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
