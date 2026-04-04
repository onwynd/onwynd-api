<?php

namespace App\Http\Controllers\API\V1\Marketing;

use App\Http\Controllers\API\BaseController;
use App\Models\Lead;
use App\Models\MarketingCampaign;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsController extends BaseController
{
    public function chart(Request $request)
    {
        $period = $request->query('period', 'Last 30 days');
        $days = 30;
        if ($period === 'Last 7 days') {
            $days = 7;
        } elseif ($period === 'Last 90 days') {
            $days = 90;
        } elseif ($period === 'Last Year') {
            $days = 365;
        }

        $startDate = Carbon::now()->subDays($days);

        // Aggregate Leads by date and source
        $leads = Lead::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, source, count(*) as count')
            ->groupBy('date', 'source')
            ->get();

        // Initialize dates
        $dateMap = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = Carbon::now()->subDays($i);
            $dateKey = $d->format('Y-m-d');
            $displayDate = $days <= 7 ? $d->format('D') : $d->format('M d');

            $dateMap[$dateKey] = [
                'date' => $displayDate,
                'facebook' => 0,
                'google' => 0,
                'linkedin' => 0,
            ];
        }

        foreach ($leads as $lead) {
            $date = $lead->date;
            if (isset($dateMap[$date])) {
                $source = strtolower($lead->source ?? '');
                if (isset($dateMap[$date][$source])) {
                    $dateMap[$date][$source] += $lead->count;
                } else {
                    if (str_contains($source, 'facebook')) {
                        $dateMap[$date]['facebook'] += $lead->count;
                    } elseif (str_contains($source, 'google')) {
                        $dateMap[$date]['google'] += $lead->count;
                    } elseif (str_contains($source, 'linkedin')) {
                        $dateMap[$date]['linkedin'] += $lead->count;
                    }
                }
            }
        }

        return $this->sendResponse(array_values($dateMap), 'Chart data retrieved successfully.');
    }

    public function index(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Aggregate metrics from campaigns
        $query = MarketingCampaign::whereNotNull('metrics');

        if ($startDate && $endDate) {
            // Filter campaigns that were active during this period
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($subQ) use ($startDate, $endDate) {
                        $subQ->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            });
        }

        $campaigns = $query->get();

        $totalImpressions = 0;
        $totalClicks = 0;
        $totalConversions = 0;
        $totalSpend = 0;

        foreach ($campaigns as $campaign) {
            $metrics = $campaign->metrics; // Assuming cast to array
            if (is_string($metrics)) {
                $metrics = json_decode($metrics, true);
            }

            if (isset($metrics['impressions'])) {
                $totalImpressions += $metrics['impressions'];
            }
            if (isset($metrics['clicks'])) {
                $totalClicks += $metrics['clicks'];
            }
            if (isset($metrics['conversions'])) {
                $totalConversions += $metrics['conversions'];
            }
            if (isset($metrics['spend'])) {
                $totalSpend += $metrics['spend'];
            }
        }

        $data = [
            'overview' => [
                'impressions' => $totalImpressions,
                'clicks' => $totalClicks,
                'conversions' => $totalConversions,
                'spend' => $totalSpend,
                'ctr' => $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2).'%' : '0%',
                'cpc' => $totalClicks > 0 ? round($totalSpend / $totalClicks, 2) : 0,
                'conversion_rate' => $totalClicks > 0 ? round(($totalConversions / $totalClicks) * 100, 2).'%' : '0%',
            ],
            // In a real scenario, you'd aggregate this over time intervals.
            // For now, we'll return the aggregated totals.
            'campaign_performance' => $campaigns->map(function ($campaign) {
                $metrics = is_string($campaign->metrics) ? json_decode($campaign->metrics, true) : $campaign->metrics;

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                    'impressions' => $metrics['impressions'] ?? 0,
                    'clicks' => $metrics['clicks'] ?? 0,
                    'conversions' => $metrics['conversions'] ?? 0,
                ];
            }),
        ];

        return $this->sendResponse($data, 'Marketing analytics retrieved successfully.');
    }
}
