<?php

abstract class DataTableRenderer
{
	public function __construct($urlParams, $name, $caption)
	{
		$this->name = $name;
		$this->caption = $caption;
		$this->page = isset($urlParams[$this->name]) ? max(1, (int) $urlParams[$this->name]) : 1;
		$this->pageCount = intval(ceil($this->count / self::$PAGE_SIZE));
		$this->offset = ($this->page - 1) * self::$PAGE_SIZE;
	}

	public function render()
	{
		echo '<h3 id="' . $this->name . '_header">' . $this->caption . ' (' . $this->count . ')</h3>';
		echo PaginationHelper::render($this->page, $this->pageCount, $this->name);
		echo '<table border="0" class="statsTable" style="border-collapse:collapse;">';
		$this->renderHeader();
		foreach ($this->data as $index => $item)
		{
			echo '<tr>';
			$this->renderItem($index, $item);
			echo '</tr>';
		}
		echo '</table>';
		echo PaginationHelper::render($this->page, $this->pageCount, $this->name);
	}

	abstract protected function renderItem(int $index, array $item): void;
	protected function renderHeader(): void {}

	protected string $name;
	protected string $caption;
	protected static $PAGE_SIZE = 100;

	protected array $data = [];
	protected int $page;
	protected int $pageCount;
	protected int $count;
	protected int $offset;
}
