<?php
namespace Bridge;

use Adapters\MagDSClient;
use Adapters\MagWSClient;

class DataPlane {
    public MagDSClient $ds;
    public MagWSClient $ws;

    public function __construct(MagDSClient $ds, MagWSClient $ws) {
        $this->ds = $ds;
        $this->ws = $ws;
    }
}
