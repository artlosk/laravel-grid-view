<?php

namespace Artlosk\LaravelGridView\Tests\Feature;

use Artlosk\LaravelGridView\DataProviders\EloquentDataProvider;
use Artlosk\LaravelGridView\GridView;
use Artlosk\LaravelGridView\Tests\Fixtures\GridRecord;
use Artlosk\LaravelGridView\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\View\ViewException;

class ActionColumnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        GridRecord::query()->create(['name' => 'Alice', 'rank' => 1]);
    }

    public function test_default_buttons_use_the_route_prefix(): void
    {
        $html = $this->actions(['route' => 'records']);

        $this->assertStringContainsString('href="http://localhost/records/1"', $html);
        $this->assertStringContainsString('href="http://localhost/records/1/edit"', $html);
        $this->assertStringContainsString('<form method="POST" action="http://localhost/records/1"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('data-grid-confirm="Delete this record?"', $html);
        $this->assertStringContainsString('title="Edit"', $html);
    }

    public function test_template_visibility_and_custom_buttons(): void
    {
        $html = $this->actions([
            'route' => 'records',
            'template' => '{view} {restore} {delete}',
            'visibleButtons' => ['delete' => fn (GridRecord $record) => $record->rank > 1],
            'buttons' => [
                'view' => ['icon' => 'fa fa-eye', 'attributes' => fn (GridRecord $record) => ['data-id' => $record->id]],
                'restore' => ['route' => 'restore', 'method' => 'POST', 'label' => 'Restore', 'confirm' => 'Sure?'],
            ],
        ]);

        $this->assertStringContainsString('<i class="fa fa-eye" aria-hidden="true"></i>', $html);
        $this->assertStringContainsString('data-id="1"', $html);
        $this->assertStringContainsString('action="http://localhost/records/1/restore"', $html);
        $this->assertStringContainsString('data-grid-confirm="Sure?"', $html);
        $this->assertStringNotContainsString('name="_method"', $html);
        $this->assertStringNotContainsString('/edit', $html);
        $this->assertStringNotContainsString('value="DELETE"', $html);
    }

    public function test_preset_attributes_and_classes_are_merged(): void
    {
        config([
            'grid-view.confirm_attribute' => 'data-confirm',
            'grid-view.action_buttons.view.attributes' => ['class' => 'icon', 'data-dialog' => 'view'],
        ]);

        $html = $this->actions([
            'route' => 'records',
            'template' => '{view} {delete}',
            'buttons' => ['view' => ['attributes' => ['class' => 'js-show']]],
            'urls' => ['view' => fn (GridRecord $record) => "/custom/{$record->id}"],
        ]);

        $this->assertStringContainsString('class="icon js-show"', $html);
        $this->assertStringContainsString('data-dialog="view"', $html);
        $this->assertStringContainsString('href="/custom/1"', $html);
        $this->assertStringContainsString('data-confirm="Delete this record?"', $html);
    }

    public function test_unknown_button_fails(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Unknown grid action button [archive]');

        $this->actions(['route' => 'records', 'template' => '{archive}']);
    }

    public function test_button_without_route_or_url_fails(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('needs a route or a url');

        $this->actions(['template' => '{view}', 'buttons' => ['view' => ['route' => null]]]);
    }

    /** @param  array<string, mixed>  $column */
    protected function actions(array $column): string
    {
        $provider = new EloquentDataProvider(GridRecord::query(), request: Request::create('/records'));

        return GridView::widget([
            'dataProvider' => $provider,
            'columns' => ['name', ['class' => 'actions', ...$column]],
        ])->toHtml();
    }
}
