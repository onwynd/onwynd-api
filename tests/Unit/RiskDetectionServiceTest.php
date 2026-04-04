<?php

namespace Tests\Unit;

use App\Services\AI\RiskDetectionService;
use PHPUnit\Framework\TestCase;

class RiskDetectionServiceTest extends TestCase
{
    public function test_detects_severe_english()
    {
        $svc = new RiskDetectionService;
        $res = $svc->analyze('I want to end my life');
        $this->assertEquals('severe', $res['risk_level']);
        $this->assertTrue($res['requires_escalation']);
    }

    public function test_detects_severe_pidgin()
    {
        $svc = new RiskDetectionService;
        $res = $svc->analyze('i wan die, i no wan live again');
        $this->assertEquals('severe', $res['risk_level']);
        $this->assertTrue($res['requires_escalation']);
    }

    public function test_detects_severe_yoruba()
    {
        $svc = new RiskDetectionService;
        $res = $svc->analyze('mo fe pa ara mi, emi o fe ye mo');
        $this->assertEquals('severe', $res['risk_level']);
        $this->assertTrue($res['requires_escalation']);
    }

    public function test_detects_severe_hausa()
    {
        $svc = new RiskDetectionService;
        $res = $svc->analyze('zan kashe kaina ban son rayuwa');
        $this->assertEquals('severe', $res['risk_level']);
        $this->assertTrue($res['requires_escalation']);
    }

    public function test_detects_severe_igbo()
    {
        $svc = new RiskDetectionService;
        $res = $svc->analyze('aga m egbu onwe m');
        $this->assertEquals('severe', $res['risk_level']);
        $this->assertTrue($res['requires_escalation']);
    }

    public function test_detects_abuse_pidgin()
    {
        $svc = new RiskDetectionService;
        $res = $svc->analyze('dem dey beat me for house');
        $this->assertContains('abuse', $res['detected_risks']);
        $this->assertTrue(in_array($res['risk_level'], ['high', 'severe']));
    }

    public function test_detects_moderate_english()
    {
        $svc = new RiskDetectionService;
        $res = $svc->analyze('I am feeling anxious and lonely lately');
        $this->assertTrue(in_array($res['risk_level'], ['moderate', 'high', 'severe']));
    }
}
