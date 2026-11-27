<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Converts an entered 12-hour time or a stored 24-hour time to HH:MM. */
function calsys_normalize_hours_time($value) {
    if (!is_string($value)) {
        return false;
    }
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
        return $value;
    }
    if (!preg_match('/^(0?[1-9]|1[0-2]):([0-5]\d)\s*([ap]m)$/i', trim($value), $matches)) {
        return false;
    }
    $hour = (int) $matches[1] % 12 + (strtolower($matches[3]) === 'pm' ? 12 : 0);
    return sprintf('%02d:%s', $hour, $matches[2]);
}

/** Formats a stored opening-hours value for its 12-hour text input. */
function calsys_hours_input_value($value) {
    if ($value === '') {
        return '';
    }
    $hour = (int) substr($value, 0, 2);
    return sprintf('%d:%s %s', $hour % 12 ?: 12, substr($value, 3, 2), $hour < 12 ? 'AM' : 'PM');
}

/** Validates one opening-hours interval or closed/unrestricted day. */
function calsys_normalize_hours($hours, $allow_unrestricted = true, $reject_unused_times = false) {
    if (!is_array($hours) || !isset($hours['mode']) || !is_string($hours['mode']) ||
        !in_array($hours['mode'], $allow_unrestricted ? array('open', 'closed', 'unrestricted') : array('open', 'closed'), true)) {
        return false;
    }
    if ($hours['mode'] !== 'open') {
        if ($reject_unused_times && (($hours['open'] ?? '') !== '' || ($hours['close'] ?? '') !== '')) {
            return false;
        }
        return array('mode' => $hours['mode'], 'open' => '', 'close' => '');
    }
    $open = calsys_normalize_hours_time($hours['open'] ?? '');
    $close = calsys_normalize_hours_time($hours['close'] ?? '');
    if ($open === false || $close === false || $open >= $close) {
        return false;
    }
    return array('mode' => 'open', 'open' => $open, 'close' => $close);
}

function calsys_normalize_room_schedule($weekly, $holidays, $reject_unused_times = false) {
    if (!is_array($weekly) || count($weekly) !== 7 || !is_array($holidays) || count($holidays) > 100) {
        return false;
    }
    $days = array();
    for ($day = 0; $day < 7; $day++) {
        $hours = calsys_normalize_hours($weekly[$day] ?? null, true, $reject_unused_times);
        if ($hours === false) {
            return false;
        }
        $days[$day] = $hours;
    }
    $exceptions = array();
    $seen = array();
    foreach ($holidays as $holiday) {
        if (!is_array($holiday) || !isset($holiday['date']) || !is_string($holiday['date'])) {
            return false;
        }
        $date = $holiday['date'];
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
        $errors = DateTimeImmutable::getLastErrors();
        $hours = calsys_normalize_hours($holiday, false, $reject_unused_times);
        if (!$parsed || ($errors && ($errors['warning_count'] || $errors['error_count'])) ||
            $parsed->format('Y-m-d') !== $date || $hours === false ||
            (isset($holiday['annual']) && !in_array($holiday['annual'], array(0, 1, '0', '1'), true))) {
            return false;
        }
        $annual = !empty($holiday['annual']) ? 1 : 0;
        $key = $annual ? 'annual:' . substr($date, 5) : 'date:' . $date;
        if (isset($seen[$key])) {
            return false;
        }
        $seen[$key] = true;
        $exceptions[] = array_merge(array('date' => $date, 'annual' => $annual), $hours);
    }
    return array('weekly' => $days, 'holidays' => $exceptions);
}

/** Resolves exact-date exceptions before annual exceptions and weekly hours. */
function calsys_room_hours_for_date($date, $rules = null) {
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
    $errors = DateTimeImmutable::getLastErrors();
    if (!$day || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $day->format('Y-m-d') !== $date) {
        return false;
    }
    $rules = $rules ?? calsys_get_settings()['booking_rules'];
    $annual = null;
    foreach ($rules['holiday_hours'] as $holiday) {
        if (!$holiday['annual'] && $holiday['date'] === $date) {
            return $holiday;
        }
        if ($holiday['annual'] && substr($holiday['date'], 5) === substr($date, 5)) {
            $annual = $holiday;
        }
    }
    return $annual ?? $rules['weekly_hours'][(int) $day->format('w')];
}

function calsys_room_is_within_hours($date, $start, $end, $rules = null) {
    $hours = calsys_room_hours_for_date($date, $rules);
    if ($hours === false || $hours['mode'] === 'closed') {
        return false;
    }
    return $hours['mode'] === 'unrestricted' || ($start >= $hours['open'] && $end <= $hours['close']);
}
