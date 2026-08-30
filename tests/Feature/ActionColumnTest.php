<?php

namespace Artlosk\LaravelGridView\Tests\Feature;

use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Artlosk\LaravelGridView\Actions\Action;
use Artlosk\LaravelGridView\Columns\ActionColumn;
use Artlosk\LaravelGridView\Columns\Column;
use Artlosk\LaravelGridView\GridView;
use Artlosk\LaravelGridView\Tests\Fixtures\GridRecord;
use Artlosk\LaravelGridView\Tests\TestCase;
use UnexpectedValueException;

class ActionColumnTest extends TestCase
{
    public function test_actions_render_as_accessible_links_forms_and_buttons(): void
    {
        $record = GridRecord::query()->create(['name' => 'Alice', 'rank' => 1]);

        Route::middleware('web')->get('/actions', function () use ($record): string {
            return GridView::make(GridRecord::query())
                ->columns([
                    Column::make('name'),
                    ActionColumn::make()->actions([
                        Action::link('show', fn (GridRecord $item) => "/records/{$item->id}")
                            ->title('Show')
                            ->icon('icon-eye')
                            ->attributes(['href' => '/unsafe-link']),
                        Action::form('delete', fn (GridRecord $item) => "/records/{$item->id}", 'DELETE')
                            ->title('Delete')
                            ->confirm('Are you sure?')
                            ->attributes([
                                'type' => 'button',
                                'formaction' => '/unsafe-submit',
                            ])
                            ->formAttributes(fn (GridRecord $item) => [
                                'class' => 'sweet-alert-form',
                                'data-confirm' => 'Delete record?',
                                'data-confirm-button' => 'Delete',
                                'data-record-id' => $item->id,
                                'action' => '/unsafe-action',
                                'method' => 'GET',
                                'data-grid-confirm' => 'Unsafe override',
                            ]),
                        Action::button('edit')
                            ->title('Edit')
                            ->attributes(fn (GridRecord $item) => [
                                'data-record-id' => $item->id,
                                'class' => 'open-modal',
                                'type' => 'submit',
                            ]),
                        Action::button('hidden')->visible(false),
                    ]),
                ])
                ->render();
        });

        $response = $this->get('/actions');

        $response->assertOk();
        $response->assertSee('href="/records/'.$record->id.'"', false);
        $response->assertSee('title="Show"', false);
        $response->assertSee('aria-label="Show"', false);
        $response->assertSee('class="icon-eye"', false);
        $response->assertSee('name="_method" value="DELETE"', false);
        $response->assertSee('data-grid-confirm="Are you sure?"', false);
        $response->assertSee('data-confirm="Delete record?"', false);
        $response->assertSee('data-confirm-button="Delete"', false);
        $response->assertSee('sweet-alert-form', false);
        $response->assertDontSee('/unsafe-action', false);
        $response->assertDontSee('Unsafe override', false);
        $response->assertSee('data-record-id="'.$record->id.'"', false);
        $response->assertSee('open-modal', false);
        $response->assertDontSee('/unsafe-link', false);
        $response->assertDontSee('/unsafe-submit', false);
        $this->assertSame(1, substr_count($response->getContent(), 'type="submit"'));
        $response->assertDontSee('aria-label="hidden"', false);
    }

    public function test_it_rejects_unsupported_http_methods(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Action::make('invalid')->method('TRACE');
    }

    public function test_link_and_form_actions_require_a_non_empty_url(): void
    {
        $record = GridRecord::query()->create(['name' => 'Alice', 'rank' => 1]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Action [broken] must return a non-empty URL.');

        Action::make('broken')->forRecord($record);
    }

    public function test_legacy_action_type_transitions_are_consistent(): void
    {
        $record = GridRecord::query()->create(['name' => 'Alice', 'rank' => 1]);
        $action = Action::make('show')->url('/records/1');

        $this->assertSame('link', $action->method('GET')->forRecord($record)['type']);
        $this->assertSame('form', $action->method('PATCH')->forRecord($record)['type']);
        $this->assertSame('button', $action->asButton()->forRecord($record)['type']);

        $link = $action->url('/records/1')->asLink()->forRecord($record);
        $this->assertSame('link', $link['type']);
        $this->assertSame('GET', $link['method']);

        $form = $action->asForm('DELETE')->forRecord($record);
        $this->assertSame('form', $form['type']);
        $this->assertSame('DELETE', $form['method']);
    }

    public function test_it_rejects_unsafe_link_schemes(): void
    {
        $record = GridRecord::query()->create(['name' => 'Alice', 'rank' => 1]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Action [unsafe] URL scheme is not allowed.');

        Action::link('unsafe', 'javascript:alert(1)')->forRecord($record);
    }

    public function test_dynamic_action_attributes_are_html_escaped(): void
    {
        GridRecord::query()->create(['name' => 'Alice', 'rank' => 1]);

        $grid = GridView::make(GridRecord::query())
            ->columns([
                ActionColumn::make()->actions([
                    Action::button('safe')
                        ->title('x" onmouseover="alert(1)')
                        ->confirm('0')
                        ->attributes(['data-label' => '<unsafe>&']),
                ]),
            ]);

        $html = $grid->render();

        $this->assertStringContainsString(
            'title="x&quot; onmouseover=&quot;alert(1)"',
            $html,
        );
        $this->assertStringContainsString('data-label="&lt;unsafe&gt;&amp;"', $html);
        $this->assertStringContainsString('data-grid-confirm="0"', $html);
        $this->assertStringNotContainsString(' onmouseover="alert(1)"', $html);
    }

    public function test_forms_accept_relative_and_same_origin_urls(): void
    {
        $record = GridRecord::query()->create(['name' => 'Alice', 'rank' => 1]);

        $relative = Action::form('relative', '/records/1')->forRecord($record);
        $sameOrigin = Action::form(
            'same-origin',
            'http://localhost/records/1',
        )->forRecord($record);

        $this->assertSame('/records/1', $relative['url']);
        $this->assertSame('http://localhost/records/1', $sameOrigin['url']);
    }

    public function test_forms_reject_cross_origin_urls(): void
    {
        $record = GridRecord::query()->create(['name' => 'Alice', 'rank' => 1]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Form action [unsafe] must use a relative or same-origin URL.');

        Action::form('unsafe', 'https://example.com/delete')->forRecord($record);
    }

    public function test_forms_reject_backslash_cross_origin_bypass(): void
    {
        $record = GridRecord::query()->create(['name' => 'Alice', 'rank' => 1]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Action [unsafe] URL contains unsafe characters.');

        Action::form('unsafe', '\\\\example.com/delete')->forRecord($record);
    }
}
