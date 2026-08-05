<?php

namespace App\Support;

class NotificationLabels
{
    /**
     * @return array<string,string>
     */
    public static function events(): array
    {
        return [
            'REQUEST_CREATED' => 'Nueva solicitud',
            'REQUEST_APPROVED' => 'Solicitud aprobada',
            'REQUEST_REJECTED' => 'Solicitud rechazada',
            'CANCELLATION_REQUESTED' => 'Cancelacion solicitada',
            'CANCELLATION_ACCEPTED' => 'Cancelacion aceptada',
            'CANCELLATION_REJECTED' => 'Cancelacion rechazada',
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function statuses(): array
    {
        return [
            'pending' => 'Pendiente de envio',
            'sent' => 'Enviado',
            'failed' => 'No enviado',
        ];
    }

    public static function event(string $event): string
    {
        return self::events()[$event] ?? 'Evento no reconocido';
    }

    public static function status(string $status): string
    {
        return self::statuses()[$status] ?? 'Estado no reconocido';
    }

    public static function ruleMessage(string $event): string
    {
        return [
            'REQUEST_CREATED' => 'Avisa al administrador que hay una nueva solicitud pendiente de revisar.',
            'REQUEST_APPROVED' => 'Avisa al empleado cuando su solicitud fue aprobada.',
            'REQUEST_REJECTED' => 'Avisa al empleado cuando su solicitud fue rechazada.',
            'CANCELLATION_REQUESTED' => 'Avisa al administrador cuando un empleado pide cancelar una ausencia.',
            'CANCELLATION_ACCEPTED' => 'Avisa al empleado cuando su cancelacion fue aceptada.',
            'CANCELLATION_REJECTED' => 'Avisa al empleado cuando su cancelacion fue rechazada.',
        ][$event] ?? 'Describe un correo automatico de la aplicacion.';
    }
}
