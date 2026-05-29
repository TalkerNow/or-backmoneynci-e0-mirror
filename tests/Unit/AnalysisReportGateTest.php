<?php

namespace Tests\Unit;

use App\Models\AnalysisReport;
use Tests\TestCase;

/**
 * Unit tests for the delivery gate on AnalysisReport: a report with an
 * arrêt critique (Gate #2 blocking rule fired) cannot be validated/delivered.
 * Pure attribute logic — no DB.
 */
class AnalysisReportGateTest extends TestCase
{
    /** @test */
    public function it_blocks_delivery_when_arret_critique_present(): void
    {
        $r = new AnalysisReport(['arret_critique_json' => ['raison' => 'x', 'codes' => ['R002']]]);
        $this->assertTrue($r->hasArretCritique());
        $this->assertFalse($r->canBeDelivered());
    }

    /** @test */
    public function it_allows_delivery_when_no_arret(): void
    {
        $r = new AnalysisReport(['arret_critique_json' => []]);
        $this->assertFalse($r->hasArretCritique());
        $this->assertTrue($r->canBeDelivered());
    }

    /** @test */
    public function it_identifies_delivery_statuts(): void
    {
        $this->assertTrue(AnalysisReport::isDeliveryStatut('valide'));
        $this->assertTrue(AnalysisReport::isDeliveryStatut('livre'));
        $this->assertFalse(AnalysisReport::isDeliveryStatut('brouillon'));
    }
}
