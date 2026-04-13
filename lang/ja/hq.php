<?php

return [
    'nav_group_store' => 'チーム・勤怠',

    'nav_pointages' => '勤怠打刻',
    'nav_weekly_planning' => '週間シフト',

    'model_attendance' => '勤怠',
    'model_attendance_plural' => '勤怠',

    'page_weekly_shift_title' => '週間シフト',

    'col_date' => '📅 日付',
    'col_staff' => '👤 スタッフ',
    'col_lunch' => '☀️ ランチ',
    'col_dinner' => '🌙 ディナー',
    'col_tip_l' => '💰 チップ（昼）',
    'col_tip_d' => '💰 チップ（夜）',
    'col_late_min' => '🐢 遅刻',
    'col_actions' => '🍄 操作',

    'filter_staff' => 'スタッフ',
    'filter_month' => '表示月',

    'action_new_punch' => '新規打刻',
    'action_edit' => '編集',
    'action_edit_mushroom' => '🍄 編集',

    'tip_eligible' => '対象',
    'tip_denied' => '除外',
    'tip_pending' => '未申請',
    'tip_no_punch' => '打刻なし',

    'notify_duplicate_attendance' => 'この日の打刻は既に存在します。編集画面を開きます。',
    'notify_duplicate_table' => 'この日の記録は既に存在します。編集へ移動します。',

    'tip_dashboard_day_total' => '日計',
    'tip_dashboard_day_total_hint' => '全スタッフ合計（昼＋夜）',

    'form_staff' => '👤 スタッフ',
    'form_date' => '📅 出勤日',
    'form_scheduled_in_lunch' => '⏰ 予定（ランチ入り）',
    'form_scheduled_in_lunch_help' => '時刻のみ。空欄の場合はその日の固定シフトから自動入力されます。',
    'form_scheduled_in_dinner' => '🏁 予定（ディナー入り）',
    'form_scheduled_in_dinner_help' => '時刻のみ。空欄の場合はその日の固定シフトから自動入力されます。',
    'form_lunch_in' => '🏃 入店（ランチ）',
    'form_lunch_out' => '🚪 退店（ランチ）',
    'form_dinner_in' => '🏃 入店（ディナー）',
    'form_dinner_out' => '🚪 退店（ディナー）',
    'form_note_in' => '📝 メモ（入り）',
    'form_note_out' => '📝 メモ（退勤）',
    'form_note_admin' => '📝 管理メモ',
    'form_late_total' => '⏱️ 遅刻合計（分）',
    'form_late_total_help' => '打刻と予定から自動計算されます。',

    'form_section_identity' => '🎮 基本情報',
    'form_section_planned' => '⏰ 予定出勤',
    'form_section_planned_desc' => '日付は上の「出勤日」を使用します。ここでは時刻のみ入力してください。',
    'form_section_lunch' => '☀️ ランチ（サービス）',
    'form_section_lunch_desc' => '日付は上の「出勤日」を使用します。打刻は時刻のみ入力してください。',
    'form_section_dinner' => '🌙 ディナー（サービス）',
    'form_section_dinner_desc' => '日付は上の「出勤日」を使用します。打刻は時刻のみ入力してください。',
    'form_section_late' => '⏱️ 遅刻',
    'form_section_notes' => '📝 メモ',

    'section_tip_title' => '🪙 チップ',
    'section_tip_desc' => 'タイムカード申請に加え、ここでチップの付与・除外を設定します。',

    'toggle_lunch_apply' => 'ランチ — チップ対象（付与）',
    'toggle_lunch_apply_help' => '打刻があり配分対象ならオンにしてください。',
    'toggle_lunch_deny' => 'ランチ — チップ除外',
    'toggle_lunch_deny_help' => 'このサービスのチップ配分から除外します。',

    'toggle_dinner_apply' => 'ディナー — チップ対象（付与）',
    'toggle_dinner_apply_help' => '打刻があり配分対象ならオンにしてください。',
    'toggle_dinner_deny' => 'ディナー — チップ除外',
    'toggle_dinner_deny_help' => 'このサービスのチップ配分から除外します。',

    'validation_clock_in_required' => 'ランチまたはディナーのいずれかの入店時刻を入力してください。',

    'day_short_mon' => '月',
    'day_short_tue' => '火',
    'day_short_wed' => '水',
    'day_short_thu' => '木',
    'day_short_fri' => '金',
    'day_short_sat' => '土',
    'day_short_sun' => '日',

    'weekly_staff' => 'スタッフ',
    'weekly_today_dot' => '今日（営業日）',
    'weekly_extra_title' => '予定外',
    'weekly_repos' => '休み',
    'weekly_section_title' => '日別・サービス別の人数',
    'weekly_section_hint_mobile' => '2列（昼｜夜）。横にスクロールで各日。',
    'weekly_section_hint_desktop' => '今日：打刻 🟢 🆘 🔴 ⚪',
    'weekly_wide_day' => '日',
    'weekly_no_schedule' => 'シフトなし',
    'weekly_extra_badge' => '予定外・打刻',
    'weekly_title_punch' => '打刻',
    'weekly_title_status' => '状態',

    'roster_heading' => '今日の打刻',
    'roster_empty' => '表示するスタッフがありません。',
    'roster_col_staff' => 'メンバー',
    'roster_col_lunch' => 'ランチ',
    'roster_col_dinner' => 'ディナー',
    'roster_meal_absent' => '欠勤',
    'roster_status_no_punch_scheduled' => '⚠ 打刻なし（予定あり）',
    'roster_status_working' => '勤務中',
    'roster_status_no_punch' => '打刻なし',
    'roster_status_no_punch_any' => '打刻なし',
    'roster_status_left' => '退勤済',
];
