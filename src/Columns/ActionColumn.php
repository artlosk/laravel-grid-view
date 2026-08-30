<?php

namespace Artlosk\LaravelGridView\Columns;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Artlosk\LaravelGridView\Actions\Action;
use Artlosk\LaravelGridView\Rows\ExpandedRow;

class ActionColumn extends Column
{
    /** @var list<Action> */
    protected array $actions = [];

    protected function __construct(string $key = 'actions')
    {
        parent::__construct($key);
        $this->label('');
        $this->cellClass('lgv-actions-cell');
        $this->headerClass('lgv-actions-cell');
    }

    public static function make(string $key = 'actions'): static
    {
        return new static($key);
    }

    /**
     * @param  list<Action>  $actions
     */
    public function actions(array $actions): static
    {
        if (!array_is_list($actions)) {
            throw new InvalidArgumentException('Actions must be a list.');
        }

        foreach ($actions as $action) {
            if (!$action instanceof Action) {
                throw new InvalidArgumentException('Every action must be an Action instance.');
            }
        }

        $this->actions = $actions;

        return $this;
    }

    public function getValue(object $record, ExpandedRow $row): HtmlString
    {
        $actions = collect($this->actions)
            ->filter(fn (Action $action) => $action->isVisible($record))
            ->map(fn (Action $action) => $action->forRecord($record))
            ->values()
            ->all();

        return new HtmlString(view('grid-view::action-column', compact('actions'))->render());
    }
}
