<?php

namespace App\Http\Controllers;

use App\Models\BoardPlugin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Drives the OAuth "web application" flow that connects a board plugin instance
 * to an external provider. Provider endpoints are provider-specific, so each
 * provider gets its own pair of methods; the resulting access token is stored
 * (encrypted) on the BoardPlugin config.
 */
class PluginOAuthController extends Controller
{
    /**
     * Send the admin to GitHub to authorize the connection.
     */
    public function githubRedirect(Request $request, BoardPlugin $boardPlugin): RedirectResponse
    {
        Gate::authorize('managePlugins', $boardPlugin->board);

        $clientId = $boardPlugin->config['client_id'] ?? config('services.github.client_id');

        if (empty($clientId)) {
            return redirect()->route('boards.show', $boardPlugin->board)
                ->with('status', __('Configurez le Client ID GitHub avant de connecter.'));
        }

        $state = Str::random(40);

        $request->session()->put('plugin_oauth', [
            'plugin_id' => $boardPlugin->id,
            'state' => $state,
        ]);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => route('plugins.oauth.github.callback'),
            'scope' => config('services.github.scopes'),
            'state' => $state,
            'allow_signup' => 'false',
        ]);

        return redirect()->away('https://github.com/login/oauth/authorize?'.$query);
    }

    /**
     * Handle GitHub's redirect back: verify state, exchange the code for a token
     * and persist it (encrypted) on the instance.
     */
    public function githubCallback(Request $request): RedirectResponse
    {
        $stored = $request->session()->pull('plugin_oauth');

        abort_unless(
            is_array($stored)
            && isset($stored['state'], $stored['plugin_id'])
            && is_string($request->query('state'))
            && hash_equals($stored['state'], (string) $request->query('state')),
            403,
        );

        $boardPlugin = BoardPlugin::findOrFail($stored['plugin_id']);
        Gate::authorize('managePlugins', $boardPlugin->board);

        $board = $boardPlugin->board;

        $code = (string) $request->query('code');

        if ($code === '' || $request->query('error')) {
            return redirect()->route('boards.show', $board)
                ->with('status', __('Connexion GitHub annulée.'));
        }

        $response = Http::acceptJson()->asForm()->post('https://github.com/login/oauth/access_token', [
            'client_id' => $boardPlugin->config['client_id'] ?? config('services.github.client_id'),
            'client_secret' => $boardPlugin->config['client_secret'] ?? config('services.github.client_secret'),
            'code' => $code,
            'redirect_uri' => route('plugins.oauth.github.callback'),
        ]);

        $token = $response->json('access_token');

        if (! $token) {
            return redirect()->route('boards.show', $board)
                ->with('status', __('Échec de la connexion GitHub.'));
        }

        $account = Http::withToken($token)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'BoardBot/1.0'])
            ->get('https://api.github.com/user')
            ->json('login');

        $config = $boardPlugin->config ?? [];
        $config['token'] = $token;
        $config['account'] = $account;

        $boardPlugin->update(['config' => $config]);

        return redirect()->route('boards.show', $board)
            ->with('status', __('GitHub connecté.'));
    }
}
