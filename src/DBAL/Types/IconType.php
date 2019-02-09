<?php

namespace EWZ\SymfonyAdminBundle\DBAL\Types;

use Fresh\DoctrineEnumBundle\DBAL\Types\AbstractEnumType;

final class IconType extends AbstractEnumType
{
    const CHECK_CIRCLE = 'check_circle';
    const COMMENT_TEXT = 'comment_text';
    const PHONE = 'phone';
    const CALENDAR = 'calendar';
    const ASSIGNMENT = 'assignment';
    const CHART = 'chart';
    const ACCOUNTS = 'accounts';
    const FILE_TEXT = 'file_text';
    const COMMENT_LIST = 'comment_list';
    const COMMENTS = 'comments';
    const VIEW_WEB = 'view_web';
    const LABEL_HEART = 'label_heart';
    const ASSIGNMENT_O = 'assignment_o';
    const HEADSET = 'headset';
    const SHARE = 'share';
    const NAVIGATION = 'navigation';
    const NOTIFICATIONS = 'notifications';
    const VOICEMAIL = 'voicemail';
    const PIN = 'pin';
    const EDIT = 'edit';
    const MAIL_SEND = 'mail_send';
    const VIDEOCAM = 'videocam';
    const PLAY = 'play';
    const LOCAL_GROCERY_STORE = 'local_grocery_store';
    const MIC = 'mic';
    const CAMERA_MIC = 'camera_mic';
    const EMAIL = 'email';
    const SCANNER = 'scanner';
    const TIME = 'time';
    const PORTABLE_WIFI = 'portable_wifi';
    const RECEIPT = 'receipt';
    const STORAGE = 'storage';
    const PLUS_CIRCLE_O = 'plus_circle_o';
    const VIEW_LIST_ALT = 'view_list_alt';
    const ALERT_OCTAGON = 'alert_octagon';
    const MOOD_BAD = 'mood_bad';
    const MONEY = 'money';
    const FLAG = 'flag';
    const BOOK = 'book';
    const COMMENT_OUTLINE = 'comment_outline';

    protected static $choices = [
        self::CHECK_CIRCLE => '<i class="zmdi zmdi-check-circle"></i>',
        self::COMMENT_TEXT => '<i class="zmdi zmdi-comment-text"></i>',
        self::PHONE => '<i class="zmdi zmdi-phone"></i>',
        self::CALENDAR => '<i class="zmdi zmdi-calendar"></i>',
        self::ASSIGNMENT => '<i class="zmdi zmdi-assignment"></i>',
        self::CHART => '<i class="zmdi zmdi-chart"></i>',
        self::ACCOUNTS => '<i class="zmdi zmdi-accounts"></i>',
        self::FILE_TEXT => '<i class="zmdi zmdi-file-text"></i>',
        self::COMMENT_LIST => '<i class="zmdi zmdi-comment-list"></i>',
        self::COMMENTS => '<i class="zmdi zmdi-comments"></i>',
        self::VIEW_WEB => '<i class="zmdi zmdi-view-web"></i>',
        self::LABEL_HEART => '<i class="zmdi zmdi-label-heart"></i>',
        self::ASSIGNMENT_O => '<i class="zmdi zmdi-assignment-o"></i>',
        self::HEADSET => '<i class="zmdi zmdi-headset"></i>',
        self::SHARE => '<i class="zmdi zmdi-share"></i>',
        self::NAVIGATION => '<i class="zmdi zmdi-navigation"></i>',
        self::NOTIFICATIONS => '<i class="zmdi zmdi-notifications"></i>',
        self::VOICEMAIL => '<i class="zmdi zmdi-voicemail"></i>',
        self::PIN => '<i class="zmdi zmdi-pin"></i>',
        self::EDIT => '<i class="zmdi zmdi-edit"></i>',
        self::MAIL_SEND => '<i class="zmdi zmdi-mail-send"></i>',
        self::VIDEOCAM => '<i class="zmdi zmdi-videocam"></i>',
        self::PLAY => '<i class="zmdi zmdi-play"></i>',
        self::LOCAL_GROCERY_STORE => '<i class="zmdi zmdi-local-grocery-store"></i>',
        self::MIC => '<i class="zmdi zmdi-mic"></i>',
        self::CAMERA_MIC => '<i class="zmdi zmdi-camera-mic"></i>',
        self::EMAIL => '<i class="zmdi zmdi-email"></i>',
        self::SCANNER => '<i class="zmdi zmdi-scanner"></i>',
        self::TIME => '<i class="zmdi zmdi-time"></i>',
        self::PORTABLE_WIFI => '<i class="zmdi zmdi-portable-wifi"></i>',
        self::RECEIPT => '<i class="zmdi zmdi-receipt"></i>',
        self::STORAGE => '<i class="zmdi zmdi-storage"></i>',
        self::PLUS_CIRCLE_O => '<i class="zmdi zmdi-plus-circle-o"></i>',
        self::VIEW_LIST_ALT => '<i class="zmdi zmdi-view-list-alt"></i>',
        self::ALERT_OCTAGON => '<i class="zmdi zmdi-alert-octagon"></i>',
        self::MOOD_BAD => '<i class="zmdi zmdi-mood-bad"></i>',
        self::MONEY => '<i class="zmdi zmdi-money"></i>',
        self::FLAG => '<i class="zmdi zmdi-flag"></i>',
        self::BOOK => '<i class="zmdi zmdi-book"></i>',
        self::COMMENT_OUTLINE => '<i class="zmdi zmdi-comment-outline"></i>',
    ];
}
