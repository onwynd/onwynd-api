<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;

class SearchController extends BaseController
{
    public function index(Request $request)
    {
        $query = $request->input('q');
        $scope = $request->input('scope', 'all');

        // Mock Search Results
        $results = [
            'query' => $query,
            'results_count' => 5,
            'results_by_category' => [
                'journals' => [],
                'moods' => [],
                'articles' => [
                    ['id' => 1, 'title' => 'Managing Stress', 'snippet' => '...stress management techniques...'],
                ],
                'posts' => [],
            ],
        ];

        return $this->sendResponse($results, 'Search results retrieved.');
    }

    public function suggestions(Request $request)
    {
        $query = $request->input('q');

        // Mock Suggestions
        $suggestions = ['meditation', 'medicine', 'medium'];

        return $this->sendResponse(['suggestions' => $suggestions], 'Search suggestions retrieved.');
    }

    public function recent()
    {
        // Mock Recent Searches
        $searches = ['anxiety', 'sleep', 'meditation'];

        return $this->sendResponse(['searches' => $searches], 'Recent searches retrieved.');
    }

    public function clearHistory()
    {
        return $this->sendResponse([], 'Search history cleared.');
    }
}
