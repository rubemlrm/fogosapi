<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\IncidentHistory;
use App\Tools\FacebookTool;
use App\Tools\HashTagTool;
use App\Tools\NotificationTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveIncidentHistory extends Job
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $incident;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct( Incident  $incident)
    {
        $this->incident = $incident;
    }

    private function saveNewIncidentHistory()
    {
        $incidentHistory = new IncidentHistory();
        $incidentHistory->id = $this->incident->id;
        $incidentHistory->sharepointId = $this->incident->sharepointId;
        $incidentHistory->aerial = $this->incident->aerial;
        $incidentHistory->terrain = $this->incident->terrain;
        $incidentHistory->location = $this->incident->location;
        $incidentHistory->man = $this->incident->man;
        $incidentHistory->save();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $last = IncidentHistory::where('id', $this->incident->id)
                                ->orderBy('created', 'desc')
                                ->limit(1)
                                ->get();

        $hashTag = HashTagTool::getHashTag($this->incident->concelho);
        $date = date("H:i");

        if(isset($last[0])){
            $last = $last[0];
            if (isset($this->incident->cos) && isset($last['cos']) && $this->incident->cos !== $last['cos']) {
                NotificationTool::sendNewCosNotification($this->incident);

                $status = "ℹ🔥{$date} - {$this->incident->location} - Novo Comandante de Operações de Socorro: {$this->incident->cos} - https://fogos.pt/fogo/{$this->incident->sadoId} {$hashTag} #FogosPT 🔥ℹ";

                $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId);

                $this->incident->lastTweetId = $lastTweetId;
                $this->incident->save();

                FacebookTool::publish($status);
                TelegramTool::publish($status);
            }

            if (isset($this->incident->POSITDescricao) && isset($last['POSITDescricao']) && $this->incident->POSITDescricao !== $last['POSITDescricao']) {
                NotificationTool::sendNewPOSITNotification($this->incident);

                $status = "ℹ🔥{$date} - {$this->incident->location} - Novo Ponto de situação: {$this->incident->POSITDescricao} - https://fogos.pt/fogo/{$this->incident->sadoId} {$hashTag} #FogosPT 🔥ℹ";

                $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId);

                $this->incident->lastTweetId = $lastTweetId;
                $this->incident->save();

                FacebookTool::publish($status);
                TelegramTool::publish($status);
            }

            if ($this->incident->man !== $last['man'] OR $this->incident->terrain !== $last['terrain'] OR $this->incident->aerial !== $last['aerial']) {
                $this->saveNewIncidentHistory();


                $diffMan = (int)$this->incident->man - (int)$last['man'];
                $diffCars = (int)$this->incident->terrain - (int)$last['terrain'];
                $diffAerial = (int)$this->incident->aerial - (int)$last['aerial'];

                $status = "Alteração de meios - MH: {$this->incident->man} (";
                if ($diffMan > 0) {
                    $status .= '+';
                }

                $status .= $diffMan . '), MT: ' . $this->incident->terrain . ' (';

                if ($diffCars > 0) {
                    $status .= '+';
                }

                $status .= $diffCars . '), MA: ' . $this->incident->aerial . '(';

                if ($diffAerial > 0) {
                    $status .= '+';
                }

                $status .= $diffAerial . ')';

                NotificationTool::send($status, $this->incident->location, $this->incident->id);
            }

            if ($this->incident->man > env('BIG_INCIDENT_MAN') && !$this->incident['notifyBig']) {
                $this->incident->notifyBig = true;
                $this->incident->save();

                $date = date("H:i");

                $status = "ℹ🚨 {$date} - {$this->incident->location} - Grande mobilização de meios:\r\n 👩‍🚒 {$this->incident->man}\r\n 🚒 {$this->incident->terrain}\r\n 🚁 {$this->incident->aerial}\r\n https://fogos.pt/fogo/{$this->incident->id} {$hashTag} @vostpt #FogosPT 🚨ℹ";

                $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId);

                $this->incident->lastTweetId = $lastTweetId;
                $this->incident->save();

                $statusf = "ℹ🚨 {$date} - {$this->incident->location} - Grande mobilização de meios:%0A  👩‍🚒 {$this->incident->man}%0A 🚒 {$this->incident->terrain}%0A 🚁 {$this->incident->aerial}%0A https://fogos.pt/fogo/{$this->incident->id} {$hashTag} #FogosPT 🚨ℹ";
                FacebookTool::publish($statusf);

                TelegramTool::publish($status);
            }

        } else {
            $this->saveNewIncidentHistory();
        }
    }
}