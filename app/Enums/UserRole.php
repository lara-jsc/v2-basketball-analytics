<?php

namespace App\Enums;

/**
 * System-level account role. Main/assistant coach are team relationships
 * (teams.main_coach_user_id, team_assistant_coaches), not roles.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Coach = 'coach';
}
