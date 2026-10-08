<?php

namespace App\Http\Controllers;

use App\Services\Jarvis\CommandHistoryService;
use App\Services\Jarvis\WebsiteRegistryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JarvisDashboardController extends Controller
{
    public function __construct(
        private readonly CommandHistoryService  $historyService,
        private readonly WebsiteRegistryService $registry,
    ) {}

    /**
     * GET /jarvis
     *
     * Render the Jarvis dashboard view.
     */
    public function index(): \Illuminate\View\View
    {
        $user         = Auth::user();
        $recentHistory = $this->historyService->recentForUser($user->id, 10);

        // Create a Sanctum token for the JS frontend to use
        // (Revokes any previous 'jarvis-web-token' first to keep it clean)
        $user->tokens()->where('name', 'jarvis-web-token')->delete();
        $webToken = $user->createToken('jarvis-web-token')->plainTextToken;

        return view('jarvis.index', [
            'user'          => $user,
            'recentHistory' => $recentHistory,
            'webToken'      => $webToken,
            'registry'      => $this->registry->all(),
        ]);
    }
}
