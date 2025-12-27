# Configuración de Cloudflare Tunnel para HTTPS Local

## 📋 Requisitos Previos
1. Cuenta de Cloudflare (gratuita)
2. Dominio registrado y configurado en Cloudflare
3. Instalar cloudflared en tu Mac

## 🚀 Instalación de cloudflared

### Opción 1: Con Homebrew (Recomendado)
```bash
brew install cloudflared
```

### Opción 2: Descarga manual
```bash
curl -L https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-darwin-amd64.tgz | tar -xz
sudo mv cloudflared /usr/local/bin
```

## 🔧 Configuración del Tunnel

### 1. Autenticación con Cloudflare
```bash
cloudflared tunnel login
```
Esto abrirá tu navegador para autorizar el acceso.

### 2. Crear un nuevo tunnel
```bash
cloudflared tunnel create noesan-dev
```

### 3. Crear archivo de configuración
Crea el archivo `~/.cloudflared/config.yml`:

```yaml
tunnel: noesan-dev
credentials-file: /Users/tuusuario/.cloudflared/[tunnel-uuid].json

ingress:
  # Ruta para el proyecto
  - hostname: noesan-dev.tudominio.com
    service: http://localhost:8000
  
  # Ruta para debugging
  - hostname: debug.noesan-dev.tudominio.com  
    service: http://localhost:8000
    
  # Catch-all rule (debe ser el último)
  - service: http_status:404
```

### 4. Configurar DNS en Cloudflare
```bash
# Para el sitio principal
cloudflared tunnel route dns noesan-dev noesan-dev.tudominio.com

# Para el subdomain de debug
cloudflared tunnel route dns noesan-dev debug.noesan-dev.tudominio.com
```

### 5. Iniciar el tunnel
```bash
cloudflared tunnel run noesan-dev
```

## 🎯 Configuración Rápida (Una sola línea)
Para desarrollo rápido sin configuración:

```bash
# Tunnel temporal (se cierra al terminar el comando)
cloudflared tunnel --url http://localhost:8000
```

Esto te dará una URL temporal como: `https://random-subdomain.trycloudflare.com`

## 📝 Scripts de Automatización

### Script para iniciar el servidor local + tunnel
Crea `start-dev.sh`:

```bash
#!/bin/bash

# Colores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Iniciando servidor de desarrollo...${NC}"

# Iniciar servidor PHP en puerto 8000
php -S localhost:8000 -t . &
PHP_PID=$!

echo -e "${GREEN}✅ Servidor PHP iniciado en http://localhost:8000${NC}"

# Esperar un momento para que el servidor se inicie
sleep 2

# Iniciar Cloudflare Tunnel
echo -e "${BLUE}🔗 Iniciando Cloudflare Tunnel...${NC}"
cloudflared tunnel --url http://localhost:8000 &
TUNNEL_PID=$!

echo -e "${GREEN}✅ Tunnel iniciado. Revisa la URL en la salida de arriba.${NC}"

# Función para limpiar procesos al salir
cleanup() {
    echo -e "\n${BLUE}🛑 Cerrando servidor y tunnel...${NC}"
    kill $PHP_PID 2>/dev/null
    kill $TUNNEL_PID 2>/dev/null
    exit 0
}

# Capturar Ctrl+C para limpieza
trap cleanup SIGINT

# Mantener el script corriendo
echo -e "${GREEN}✅ Todo listo! Presiona Ctrl+C para cerrar.${NC}"
wait
```

### Hacer el script ejecutable
```bash
chmod +x start-dev.sh
```

## 🔄 Uso Diario

### Para desarrollo rápido:
```bash
./start-dev.sh
```

### Para tunnel permanente:
```bash
# Terminal 1: Servidor local
php -S localhost:8000 -t .

# Terminal 2: Tunnel persistente
cloudflared tunnel run noesan-dev
```

## 🌐 URLs de Acceso

Con el tunnel configurado tendrás:
- **Local:** `http://localhost:8000`
- **HTTPS Público:** `https://noesan-dev.tudominio.com`
- **Debug HTTPS:** `https://debug.noesan-dev.tudominio.com`

## 📊 Endpoints de Testing

Con HTTPS habilitado podrás testear:

### Debug Endpoint
```bash
# Test básico
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}' \
  https://noesan-dev.tudominio.com/assets/conversions/debug-endpoint.php

# Meta conversions
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"event_name": "Contact", "email": "test@ejemplo.com"}' \
  https://noesan-dev.tudominio.com/assets/conversions/meta-endpoint.php
```

### Desde JavaScript (con HTTPS)
```javascript
// Ya no hay problemas de CORS o Mixed Content
fetch('https://noesan-dev.tudominio.com/assets/conversions/debug-endpoint.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ test: 'desde javascript con https' })
});
```

## 🛠️ Troubleshooting

### Si el tunnel no funciona:
```bash
# Verificar estado del tunnel
cloudflared tunnel info noesan-dev

# Ver logs
cloudflared tunnel run noesan-dev --loglevel debug
```

### Reiniciar tunnel:
```bash
# Matar todos los procesos de cloudflared
pkill cloudflared

# Volver a iniciar
cloudflared tunnel run noesan-dev
```

## 🔒 Consideraciones de Seguridad

- Los tunnels temporales (`--url`) son públicos
- Para producción, usa tunnels con autenticación
- No expongas datos sensibles en development
- Usa diferentes subdominios para testing vs producción