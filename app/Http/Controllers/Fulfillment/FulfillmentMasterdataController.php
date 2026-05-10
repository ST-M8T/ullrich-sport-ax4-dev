<?php

namespace App\Http\Controllers\Fulfillment;

use App\Application\Fulfillment\Masterdata\Queries\GetFulfillmentMasterdataCatalog;
use Illuminate\Contracts\View\View;

final class FulfillmentMasterdataController
{
    public function __construct(private readonly GetFulfillmentMasterdataCatalog $getCatalog) {}

    public function index(): View
    {
        $catalog = ($this->getCatalog)();

        return view('fulfillment.masterdata.index', [
            'catalog' => $catalog,
        ]);
    }
}
