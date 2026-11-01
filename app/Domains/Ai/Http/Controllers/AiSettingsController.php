<?php

namespace App\Domains\Ai\Http\Controllers;

use App\Domains\Admin\Actions\RecordAdminAction;
use App\Domains\Ai\Services\AiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiSettingsController extends Controller
{
    public function edit(AiSettings $settings): View
    {
        return view('admin.ai-settings', [
            'values' => $settings->formValues(),
            'providers' => AiSettings::providers(),
            'tasks' => AiSettings::tasks(),
        ]);
    }

    public function update(Request $request, AiSettings $settings, RecordAdminAction $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'provider' => ['required', 'string', Rule::in(array_keys((array) config('prism.providers', [])))],
            'model' => ['required', 'string', 'max:190'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'timeout' => ['required', 'integer', 'min:1', 'max:120'],
            'retries' => ['required', 'integer', 'min:0', 'max:5'],
            'feature_limits' => ['nullable', 'array'],
            'feature_limits.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $changed = $settings->save([
            'enabled' => $request->boolean('enabled'),
            'provider' => $data['provider'],
            'model' => $data['model'],
            'api_key' => $data['api_key'] ?? null,
            'timeout' => $data['timeout'],
            'retries' => $data['retries'],
            'feature_limits' => $data['feature_limits'] ?? [],
        ]);

        $audit->handle(
            $request->user(),
            'ai.settings.updated',
            null,
            $changed === [] ? 'No changes.' : 'Changed: '.implode(', ', $changed).'.',
        );

        return back()->with('status', 'AI settings saved.');
    }

    public function test(Request $request, AiSettings $settings, RecordAdminAction $audit): RedirectResponse
    {
        $result = $settings->testConnection();

        $audit->handle(
            $request->user(),
            'ai.settings.tested',
            null,
            $result['provider'].' / '.$result['model'].': '.($result['ok'] ? 'success' : 'failed').' — '.$result['message'],
        );

        if (! $result['ok']) {
            return back()->withErrors(['ai_test' => $result['message']]);
        }

        return back()->with('status', $result['message']);
    }
}
