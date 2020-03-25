<?php

namespace Sendportal\Base\Http\Controllers;

use Sendportal\Base\Http\Requests\SubscribersImportRequest;
use Sendportal\Base\Repositories\SegmentTenantRepository;
use Sendportal\Base\Services\Subscribers\ImportSubscriberService;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Reader\Exception\ReaderNotOpenedException;
use Exception;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

class SubscribersImportController extends Controller
{
    /** @var ImportSubscriberService */
    protected $subscriberService;

    public function __construct(ImportSubscriberService $subscriberService)
    {
        $this->subscriberService = $subscriberService;
    }

    /**
     * @throws Exception
     */
    public function show(SegmentTenantRepository $segmentRepo): ViewContract
    {
        $segments = $segmentRepo->pluck(currentTeamId(), 'name', 'id');

        return view('subscribers.import', compact('segments'));
    }

    /**
     * @throws IOException
     * @throws UnsupportedTypeException
     * @throws ReaderNotOpenedException
     */
    public function store(SubscribersImportRequest $request): RedirectResponse
    {
        if ($request->file('file')->isValid()) {
            $filename = str_random(16) . '.csv';

            $path = $request->file('file')->storeAs('imports', $filename);

            $subscribers = (new FastExcel)->import(storage_path('app/' . $path), function (array $line) use ($request) {
                // TODO: validate each row beforehand
                try {
                    $data = array_only($line, ['id', 'email', 'first_name', 'last_name']);

                    $data['segments'] = $request->get('segments') ?? [];

                    return $this->subscriberService->import(currentTeamId(), $data);
                } catch (Exception $e) {
                    Log::warning($e->getMessage());
                }

                return null;
            });

            Storage::disk('local')->delete('imports/' . $filename);

            return redirect()->route('subscribers.index')
                ->with('success', __('Imported :count subscriber(s)', ['count' => $subscribers->count()]));
        }

        return redirect()->route('subscribers.index')
            ->with('errors', __('The uploaded file is not valid'));
    }
}