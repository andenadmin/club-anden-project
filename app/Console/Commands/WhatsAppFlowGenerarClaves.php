<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use phpseclib3\Crypt\RSA;

class WhatsAppFlowGenerarClaves extends Command
{
    protected $signature = 'whatsapp-flows:generar-claves
                            {--dir= : Carpeta donde guardar los .pem (por defecto storage/app/private/whatsapp-flows)}
                            {--passphrase= : Protege la clave privada con una contraseña}';

    protected $description = 'Genera el par de claves RSA 2048 para el endpoint de WhatsApp Flows (no las sube a Meta).';

    public function handle(): int
    {
        $dir = $this->option('dir') ?: storage_path('app/private/whatsapp-flows');
        $privatePath = $dir . DIRECTORY_SEPARATOR . 'private.pem';
        $publicPath  = $dir . DIRECTORY_SEPARATOR . 'public.pem';

        if (file_exists($privatePath) && !$this->confirm("Ya existe {$privatePath}. ¿Reemplazarla? (la anterior deja de servir)", false)) {
            return self::FAILURE;
        }

        if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
            $this->error("No se pudo crear {$dir}");
            return self::FAILURE;
        }

        $key = RSA::createKey(2048);
        if ($pass = $this->option('passphrase')) {
            $key = $key->withPassword($pass);
        }

        $privatePem = $key->toString('PKCS8');
        $publicPem  = $key->getPublicKey()->toString('PKCS8');

        file_put_contents($privatePath, $privatePem);
        @chmod($privatePath, 0600);
        file_put_contents($publicPath, $publicPem);

        $this->info("Clave privada: {$privatePath}");
        $this->info("Clave pública: {$publicPath}");
        $this->newLine();
        $this->line('Agregá al .env del servidor (una sola línea, en base64):');
        $this->line('WA_FLOW_PRIVATE_KEY=' . base64_encode($privatePem));
        if ($pass) {
            $this->line('WA_FLOW_PRIVATE_KEY_PASSPHRASE=<la contraseña que usaste>');
        }
        $this->newLine();
        $this->warn('No subas private.pem al repo ni lo compartas. La pública se sube al número (ver docs/whatsapp-flow-reserva/README.md).');

        return self::SUCCESS;
    }
}
