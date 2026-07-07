<?php

namespace App\Http\Controllers;

use App\Models\BoardPlugin;
use Board\PluginSdk\Contracts\ProvidesOAuth;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Provider-agnostic OAuth "web application" broker connecting a board plugin
 * instance to an external provider. The plugin (a {@see ProvidesOAuth}) declares
 * its endpoints, scopes and how to read back the account; the host owns the flow:
 * state handling, the authorize redirect, the code→token exchange and storing the
 * (encrypted) token on the instance config. Nothing here is provider-specific.
 */
class PluginOAuthController extends Controller
{
    /**
     * Send the admin to the provider's authorize screen.
     */
    public function redirect(Request $request, BoardPlugin $boardPlugin): RedirectResponse
    {
        Gate::authorize('managePlugins', $boardPlugin->board);

        $plugin = $this->oauthPlugin($boardPlugin);

        if ($plugin === null) {
            return redirect()->route('boards.show', $boardPlugin->board)
                ->with('status', __('Ce Power-Up ne gère pas la connexion OAuth.'));
        }

        $provider = $plugin->oauthProvider();

        $clientId = $boardPlugin->config['client_id'] ?? config("services.{$provider}.client_id");

        if (empty($clientId)) {
            return redirect()->route('boards.show', $boardPlugin->board)
                ->with('status', __('Configurez les identifiants OAuth avant de connecter.'));
        }

        $state = Str::random(40);

        $request->session()->put('plugin_oauth', [
            'plugin_id' => $boardPlugin->id,
            'state' => $state,
        ]);

        $query = http_build_query(array_merge($plugin->authorizeParameters(), [
            'client_id' => $clientId,
            'redirect_uri' => route('plugins.oauth.callback'),
            'scope' => implode(' ', $plugin->scopes()),
            'state' => $state,
        ]));

        return redirect()->away($plugin->authorizeUrl().'?'.$query);
    }

    /**
     * Handle the provider's redirect back: verify state, exchange the code for a
     * token and persist it (encrypted) on the instance.
     */
    public function callback(Request $request): RedirectResponse
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
        $plugin = $this->oauthPlugin($boardPlugin);

        if ($plugin === null) {
            return redirect()->route('boards.show', $board)
                ->with('status', __('Ce Power-Up ne gère pas la connexion OAuth.'));
        }

        $provider = $plugin->oauthProvider();

        $code = (string) $request->query('code');

        if ($code === '' || $request->query('error')) {
            return redirect()->route('boards.show', $board)
                ->with('status', __('Connexion annulée.'));
        }

        $token = Http::acceptJson()->asForm()->post($plugin->tokenUrl(), [
            'client_id' => $boardPlugin->config['client_id'] ?? config("services.{$provider}.client_id"),
            'client_secret' => $boardPlugin->config['client_secret'] ?? config("services.{$provider}.client_secret"),
            'code' => $code,
            'redirect_uri' => route('plugins.oauth.callback'),
        ])->json('access_token');

        if (! $token) {
            return redirect()->route('boards.show', $board)
                ->with('status', __('Échec de la connexion.'));
        }

        $config = $boardPlugin->config ?? [];
        $config['token'] = $token;
        $config['account'] = $plugin->resolveAccount($token);

        $boardPlugin->update(['config' => $config]);

        return redirect()->route('boards.show', $board)
            ->with('status', __('Connecté.'));
    }

    /**
     * Resolve the installed plugin behind an instance, or null when it is missing
     * or does not drive OAuth (e.g. an outdated build without ProvidesOAuth).
     */
    private function oauthPlugin(BoardPlugin $boardPlugin): ?ProvidesOAuth
    {
        $plugin = app(PluginRegistry::class)->get($boardPlugin->plugin_key);

        return $plugin instanceof ProvidesOAuth ? $plugin : null;
    }
}
