/**
 * Mapeo de identificadores enum del BotEngine a etiquetas humanas para mostrar
 * en el panel del asesor. Mantener sincronizado con `app/Services/BotEngine.php`.
 */

export const ESTADO_LABELS: Record<string, string> = {
    INICIO:             'Inicio',
    REGISTRO_CLIENTE:   'Registrando cliente',
    MENU_PRINCIPAL:     'En menú principal',
    RECOLECTANDO_DATOS: 'Recolectando datos',
    CONFIRMACION:       'Esperando confirmación',
    COMPLETADO:         'Reserva completada',
    CAMBIANDO_DATO:     'Modificando un dato',
    PAUSADO:            'Pausado',
};

export const RAMA_LABELS: Record<string, string> = {
    DEPORTES:    'Deportes',
    RESTAURANTE: 'Restaurante',
    EVENTOS:     'Eventos',
};

export const SUBTIPO_LABELS: Record<string, string> = {
    NINOS:       'Cumpleaños infantil',
    GENERAL_EVT: 'Evento general',
};

export const STEP_LABELS: Record<string, string> = {
    fecha:                      'Fecha',
    hora_inicio:                'Hora de inicio',
    tipo_evento:                'Tipo de evento',
    nombre_responsable_custom:  'Nombre del responsable',
    pack_seleccionado:          'Pack elegido',
    menu_preferido:             'Menú preferido',
    menu_adultos:               'Menú adultos',
};

export const MOTIVO_PAUSA_LABELS: Record<string, string> = {
    SOLICITUD_CLIENTE:             'Solicitud del cliente',
    OPCIONES_INVALIDAS_REITERADAS: 'Opciones inválidas reiteradas',
    CAPACIDAD_EXCEDIDA:            'Capacidad excedida',
    ASESOR_TAKEOVER:               'Tomado por asesor',
};

/**
 * Convierte un identificador enum/snake_case a su etiqueta humana.
 * Si no hay traducción, fallback a la versión "snake case" → "snake case" en lower.
 */
export function labelize(raw: string | null | undefined, map: Record<string, string>): string {
    if (!raw) return '—';
    return map[raw] ?? raw.replaceAll('_', ' ').toLowerCase();
}

/**
 * Helper específico para steps con sufijos dinámicos (ej. `adicional_qty_42`).
 */
export function labelizeStep(raw: string | null | undefined): string {
    if (!raw) return '—';
    if (STEP_LABELS[raw]) return STEP_LABELS[raw];
    if (raw.startsWith('adicional_qty_')) return 'Cantidad de adicional';
    return raw.replaceAll('_', ' ').toLowerCase();
}
