<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\OpeningHour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningHourController extends Controller
{
    public function update(Request $request, $id)
    {
        $business = Business::find($id);

        if (!$business) {
            return response()->json(['message' => 'Fiche introuvable.'], 404);
        }

        if ($business->owner_id != $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez modifier que les horaires de vos propres fiches.'], 403);
        }

        $data = $request->validate([
            'hours'               => 'present|array|max:50',
            'hours.*.day_of_week' => 'required|integer|between:1,7',
            'hours.*.opens_at'    => 'required|date_format:H:i',
            'hours.*.closes_at'   => 'required|date_format:H:i',
        ]);

        $errors = [];
        $byDay = [];

        foreach ($data['hours'] as $i => $h) {
            if ($h['opens_at'] >= $h['closes_at']) {
                $errors["hours.$i.closes_at"] = ["L'heure de fermeture doit être après l'heure d'ouverture."];
            }
            $byDay[$h['day_of_week']][] = ['i' => $i, 'opens_at' => $h['opens_at'], 'closes_at' => $h['closes_at']];
        }

        foreach ($byDay as $slots) {
            usort($slots, fn($a, $b) => strcmp($a['opens_at'], $b['opens_at']));
            for ($k = 1; $k < count($slots); $k++) {
                if ($slots[$k]['opens_at'] < $slots[$k - 1]['closes_at']) {
                    $errors["hours.{$slots[$k]['i']}.opens_at"] = ['Cette plage chevauche une autre plage du même jour.'];
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($business, $data) {
            OpeningHour::where('business_id', $business->id)->delete();

            foreach ($data['hours'] as $h) {
                OpeningHour::create([
                    'business_id' => $business->id,
                    'day_of_week' => $h['day_of_week'],
                    'opens_at'    => $h['opens_at'],
                    'closes_at'   => $h['closes_at'],
                ]);
            }
        });

        return OpeningHour::where('business_id', $business->id)
            ->orderBy('day_of_week')
            ->orderBy('opens_at')
            ->get(['id', 'business_id', 'day_of_week', 'opens_at', 'closes_at']);
    }
}