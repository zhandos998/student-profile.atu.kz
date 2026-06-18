<?php

namespace Tests\Unit;

use App\Models\AcademicProfile;
use App\Models\HealthPassport;
use App\Models\PsychologicalProfile;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentRiskService;
use PHPUnit\Framework\TestCase;

class StudentRiskServiceTest extends TestCase
{
    public function test_it_calculates_profile_completion_and_dashboard_reasons(): void
    {
        $service = new StudentRiskService();
        $user = new User();
        $user->setRelation('academicProfile', new AcademicProfile([
            'gpa' => 2.1,
            'academic_debt' => 'No',
        ]));

        $profile = new StudentProfile([
            'full_name' => 'Student Name',
            'birth_date' => '2005-01-01',
            'faculty' => 'Faculty',
            'group_name' => 'IS-101',
        ]);
        $profile->setRelation('user', $user);

        $this->assertSame(50, $service->profileCompletion($profile));
        $this->assertContains('GPA ниже 2.5', $service->dashboardRiskReasons($profile));
        $this->assertFalse($service->hasAcademicDebt('No'));
        $this->assertTrue($service->hasAcademicDebt('Calculus'));
    }

    public function test_it_returns_social_psychological_and_medical_risk_reasons(): void
    {
        $service = new StudentRiskService();

        $socialProfile = new StudentProfile([
            'is_low_income' => true,
            'benefits' => ['asp'],
            'social_support_need_status' => 'needs',
            'social_support_need_details' => 'Dormitory payment',
        ]);

        $this->assertContains('Малообеспеченная семья', $service->socialRiskReasons($socialProfile));

        $user = new User();
        $user->setRelation('psychologicalProfile', new PsychologicalProfile([
            'testing_results' => 'Risk marker',
        ]));
        $user->setRelation('healthPassport', new HealthPassport([
            'psychological_diagnosis' => 'Diagnosis',
        ]));

        $this->assertContains('Результаты тестирований', $service->psychologicalRiskReasons($user));

        $medicalReasons = $service->medicalRiskReasons(new HealthPassport([
            'diagnosis' => 'Medical risk',
            'dispensary_accounting' => true,
        ]));

        $this->assertContains('Диспансерный учет', $medicalReasons);
    }
}
