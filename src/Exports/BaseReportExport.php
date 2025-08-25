<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
class BaseReportExport implements FromView, ShouldAutoSize
{
    protected $data;
    protected $params;
    protected $viewName;

    public function __construct($data, $params, $viewName)
    {
        $this->data = $data;
        $this->params = $params;
        $this->viewName = $viewName;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        // Return the view with the data to be exported
        return view($this->viewName, [
            'data' => $this->data,
            'params' => $this->params
        ]);
    }
}
