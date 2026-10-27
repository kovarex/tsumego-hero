<?php

namespace App\Utility;

use PaginationHelper;

abstract class DataTableRenderer
{
	//$caption: The caption of the table, the count is added to it; null for none, when the page names the list already
	public function __construct(array $urlParams, string $name, ?string $caption)
	{
		$this->name = $name;
		$this->caption = $caption;
		$this->page = isset($urlParams[$this->name]) ? max(1, (int) $urlParams[$this->name]) : 1;
		$this->pageCount = intval(ceil($this->count / self::$PAGE_SIZE));
		$this->offset = ($this->page - 1) * self::$PAGE_SIZE;
	}

	public function render(): void
	{
		echo PaginationHelper::render($this->page, $this->pageCount, $this->name);
		echo '<table border="0" class="data-table data-table--compact" style="border-collapse:collapse;">';
		if ($this->caption !== null)
			echo '<caption id="' . $this->name . '_header">' . $this->caption . ' (' . $this->count . ')</caption>';
		$this->renderHeader();
		foreach ($this->data as $index => $item)
		{
			$class = $this->rowClass($item);
			echo $class ? '<tr class="' . $class . '">' : '<tr>';
			$this->renderItem($index, $item);
			echo '</tr>';
		}
		echo '</table>';
		echo PaginationHelper::render($this->page, $this->pageCount, $this->name);
	}

	abstract protected function renderItem(int $index, array $item): void;
	protected function renderHeader(): void {}
	protected function rowClass(array $item): string
	{
		return '';
	}

	protected string $name;
	protected ?string $caption;
	protected static int $PAGE_SIZE = 100;

	protected array $data = [];
	protected int $page;
	protected int $pageCount;
	protected int $count;
	protected int $offset;
}
