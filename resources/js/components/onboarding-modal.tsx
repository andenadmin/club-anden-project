import { HelpCircle, X } from 'lucide-react';
import { useState } from 'react';
import { Dialog, DialogContent } from '@/components/ui/dialog';

const ONBOARDING_MD = `
# Guía rápida del panel de reservas

> Leé esto una sola vez y vas a entender todo lo que necesitás para arrancar.

---

## 1. ¿Qué hace el bot?

**Andy** es el asistente de WhatsApp que atiende a los clientes las 24 hs. Él toma los datos de la reserva paso a paso (fecha, hora, personas, sector, nombre, mail) y la confirma automáticamente.

Vos, como asesor, **solo intervenís cuando el bot no puede resolver algo solo**.

---

## 2. Cuándo te llega una alerta 🔔

La campana del panel suena en estos casos:

| Situación | Qué pasó |
|---|---|
| **"Quiero hablar con un asesor"** | El cliente pidió atención humana |
| **Opciones inválidas repetidas** | El cliente no entendió el flujo del bot |
| **Evento / Cumpleaños** | Toda reserva de evento requiere revisión manual |
| **Sector sin cupo** | El bot pausó porque todos los sectores están llenos |

---

## 3. Cómo manejar cada situación

**El cliente pidió un asesor**
→ Abrí la conversación en /bandeja · respondé lo que necesite · cuando terminés presioná **"Solucionado / Reanudar bot"**

**El cliente no responde bien al bot**
→ Revisá en qué paso quedó (ves el estado en el panel derecho) · contestale vos directamente · luego reanudar bot

**Reserva de evento o cumpleaños**
→ El bot ya tomó los datos básicos · tu tarea es revisar el presupuesto estimado · confirmar disponibilidad · y avisarle al cliente por WhatsApp o teléfono

**Reserva pendiente de confirmación**
→ En /reservas vas a ver el estado **PENDIENTE** · confirmala o cancelala desde ahí · el cliente recibe aviso automático

---

## 4. Problemas frecuentes

**"El cliente dice que no le llegó la confirmación"**
→ Verificá en la conversación que el bot haya enviado el mensaje de confirmación · si no llegó, reenvialelo manualmente desde la bandeja

**"El cliente quiere cancelar"**
→ Ingresá a /reservas · buscá la reserva · cambiá el estado a CANCELADA

**"El cliente quiere cambiar la fecha o el sector"**
→ Editá directamente la reserva en /reservas (ícono del lápiz) · avisale al cliente el cambio por WhatsApp

**"El bot dejó de responder"**
→ Revisá el estado de la sesión en la bandeja · si dice PAUSADO podés reanudar manualmente · si hay un error técnico avisá al equipo

---

## 5. Buenas prácticas ✅

- [ ] Revisar las reservas del día antes de abrir el turno
- [ ] Leer los comentarios especiales de cada reserva
- [ ] Confirmar manualmente las reservas de grupos grandes (+8 personas)
- [ ] Prestar atención a pedidos de sillas de bebé o necesidades especiales
- [ ] Si el bot escala a asesor, responder en menos de 30 minutos
- [ ] Reanudar el bot siempre que termines de atender a un cliente
- [ ] Avisar al equipo si ves algo raro en las reservas (horarios duplicados, datos incorrectos)

---

## 6. Para recordar 📌

- [ ] El bot trabaja solo — vos solo intervenís cuando hay alerta 🔔
- [ ] Siempre presionar **"Reanudar bot"** al terminar una atención
- [ ] Los eventos siempre necesitan revisión manual
- [ ] Podés editar cualquier reserva desde /reservas
- [ ] Las alertas de capacidad las gestiona el equipo de coordinación
`;

function renderMarkdown(md: string): React.ReactNode[] {
    const lines = md.split('\n');
    const nodes: React.ReactNode[] = [];
    let key = 0;
    let i = 0;

    while (i < lines.length) {
        const line = lines[i];

        if (line.startsWith('# ')) {
            nodes.push(<h1 key={key++} className="text-xl font-bold mb-3 mt-1">{line.slice(2)}</h1>);
        } else if (line.startsWith('## ')) {
            nodes.push(<h2 key={key++} className="text-base font-semibold mt-5 mb-2 text-foreground border-b pb-1">{line.slice(3)}</h2>);
        } else if (line.startsWith('> ')) {
            nodes.push(<blockquote key={key++} className="border-l-4 border-primary/40 pl-3 text-sm text-muted-foreground italic my-2">{line.slice(2)}</blockquote>);
        } else if (line.startsWith('---')) {
            nodes.push(<hr key={key++} className="my-3 border-border" />);
        } else if (line.startsWith('| ')) {
            // tabla
            const tableLines: string[] = [];
            while (i < lines.length && lines[i].startsWith('|')) {
                tableLines.push(lines[i]);
                i++;
            }
            const [header, , ...rows] = tableLines;
            const headers = header.split('|').filter(Boolean).map(h => h.trim());
            nodes.push(
                <div key={key++} className="overflow-x-auto my-3">
                    <table className="w-full text-sm border-collapse">
                        <thead>
                            <tr className="bg-muted/50">
                                {headers.map((h, j) => <th key={j} className="text-left px-3 py-1.5 font-medium border border-border">{h}</th>)}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, ri) => (
                                <tr key={ri} className="border-b border-border">
                                    {row.split('|').filter(Boolean).map((cell, ci) => (
                                        <td key={ci} className="px-3 py-1.5 border border-border">
                                            {renderInline(cell.trim())}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            );
            continue;
        } else if (line.match(/^- \[[ x]\] /)) {
            // checklist
            const items: string[] = [];
            while (i < lines.length && lines[i].match(/^- \[[ x]\] /)) {
                items.push(lines[i]);
                i++;
            }
            nodes.push(
                <ul key={key++} className="space-y-1 my-2">
                    {items.map((item, j) => (
                        <li key={j} className="flex items-start gap-2 text-sm">
                            <span className="mt-0.5 w-4 h-4 rounded border border-border shrink-0 bg-muted/30 flex items-center justify-center text-[10px]">
                                {item.includes('[x]') ? '✓' : ''}
                            </span>
                            <span>{renderInline(item.replace(/^- \[[ x]\] /, ''))}</span>
                        </li>
                    ))}
                </ul>
            );
            continue;
        } else if (line.startsWith('- ')) {
            const items: string[] = [];
            while (i < lines.length && lines[i].startsWith('- ')) {
                items.push(lines[i]);
                i++;
            }
            nodes.push(
                <ul key={key++} className="list-disc list-inside space-y-0.5 my-2 text-sm text-muted-foreground">
                    {items.map((item, j) => <li key={j}>{renderInline(item.slice(2))}</li>)}
                </ul>
            );
            continue;
        } else if (line.startsWith('**') && line.endsWith('**') === false && line.includes('**')) {
            nodes.push(<p key={key++} className="text-sm my-1">{renderInline(line)}</p>);
        } else if (line.startsWith('→')) {
            nodes.push(<p key={key++} className="text-sm text-muted-foreground ml-3 my-0.5">{renderInline(line)}</p>);
        } else if (line.trim() !== '') {
            nodes.push(<p key={key++} className="text-sm my-1">{renderInline(line)}</p>);
        }

        i++;
    }

    return nodes;
}

function renderInline(text: string): React.ReactNode {
    const parts = text.split(/(\*\*[^*]+\*\*|`[^`]+`)/g);
    return parts.map((part, i) => {
        if (part.startsWith('**') && part.endsWith('**'))
            return <strong key={i}>{part.slice(2, -2)}</strong>;
        if (part.startsWith('`') && part.endsWith('`'))
            return <code key={i} className="bg-muted px-1 rounded text-xs font-mono">{part.slice(1, -1)}</code>;
        return part;
    });
}

export function OnboardingButton() {
    const [open, setOpen] = useState(false);

    return (
        <>
            <button
                onClick={() => setOpen(true)}
                title="Guía rápida"
                className="inline-flex items-center justify-center w-7 h-7 rounded-full text-muted-foreground hover:text-foreground hover:bg-accent transition-colors"
            >
                <HelpCircle className="size-4" />
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-2xl max-h-[85vh] flex flex-col p-0 gap-0">
                    <div className="flex items-center justify-between px-5 py-3 border-b shrink-0">
                        <h2 className="text-sm font-semibold">Guía rápida del panel</h2>
                        <button onClick={() => setOpen(false)} className="text-muted-foreground hover:text-foreground">
                            <X className="size-4" />
                        </button>
                    </div>
                    <div className="overflow-y-auto px-5 py-4 flex-1">
                        {renderMarkdown(ONBOARDING_MD)}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
