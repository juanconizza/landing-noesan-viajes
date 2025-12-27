#!/bin/bash

# Colores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Iniciando entorno de desarrollo Noe San Viajes...${NC}"
echo -e "${YELLOW}📍 Directorio: $(pwd)${NC}"

# Verificar si estamos en el directorio correcto
if [[ ! -f "index.php" ]]; then
    echo -e "${RED}❌ Error: No se encuentra index.php. ¿Estás en el directorio correcto?${NC}"
    exit 1
fi

# Verificar si PHP está instalado
if ! command -v php &> /dev/null; then
    echo -e "${RED}❌ Error: PHP no está instalado${NC}"
    exit 1
fi

# Verificar si cloudflared está instalado
if ! command -v cloudflared &> /dev/null; then
    echo -e "${YELLOW}⚠️  cloudflared no está instalado. Solo se iniciará el servidor local.${NC}"
    echo -e "${YELLOW}   Para instalar: brew install cloudflared${NC}"
    CLOUDFLARED_AVAILABLE=false
else
    CLOUDFLARED_AVAILABLE=true
fi

# Puerto para el servidor
PORT=8000

# Verificar si el puerto está disponible
if lsof -i:$PORT &> /dev/null; then
    echo -e "${YELLOW}⚠️  Puerto $PORT está ocupado. Intentando cerrar procesos...${NC}"
    pkill -f "php -S localhost:$PORT" 2>/dev/null
    sleep 2
fi

# Crear directorio de logs si no existe
mkdir -p assets/conversions/logs

# Iniciar servidor PHP
echo -e "${BLUE}🌐 Iniciando servidor PHP en puerto $PORT...${NC}"
php -S localhost:$PORT -t . > /dev/null 2>&1 &
PHP_PID=$!

# Verificar que el servidor se inició correctamente
sleep 2
if ! kill -0 $PHP_PID 2>/dev/null; then
    echo -e "${RED}❌ Error: No se pudo iniciar el servidor PHP${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Servidor PHP iniciado correctamente${NC}"
echo -e "${GREEN}   URL Local: http://localhost:$PORT${NC}"

# URLs importantes
echo -e "\n${BLUE}📋 URLs importantes:${NC}"
echo -e "   🏠 Página principal: ${GREEN}http://localhost:$PORT${NC}"
echo -e "   🔍 Debug endpoint: ${GREEN}http://localhost:$PORT/assets/conversions/debug-endpoint.php${NC}"
echo -e "   🎯 Meta endpoint: ${GREEN}http://localhost:$PORT/assets/conversions/meta-endpoint.php${NC}"
echo -e "   🧪 Debug tester: ${GREEN}http://localhost:$PORT/assets/conversions/debug-tester.html${NC}"
echo -e "   📊 Test endpoint: ${GREEN}http://localhost:$PORT/assets/conversions/test-endpoint.html${NC}"

# Iniciar Cloudflare Tunnel si está disponible
if [ "$CLOUDFLARED_AVAILABLE" = true ]; then
    echo -e "\n${BLUE}🔗 Iniciando Cloudflare Tunnel...${NC}"
    echo -e "${YELLOW}   Generando URL pública HTTPS...${NC}"
    
    # Iniciar tunnel en segundo plano y capturar la URL
    cloudflared tunnel --url http://localhost:$PORT 2>&1 | while read line; do
        if [[ $line == *"https://"*.trycloudflare.com ]]; then
            TUNNEL_URL=$(echo $line | grep -o 'https://[^[:space:]]*')
            echo -e "\n${GREEN}🌍 URL Pública HTTPS: $TUNNEL_URL${NC}"
            echo -e "${GREEN}   Debug público: $TUNNEL_URL/assets/conversions/debug-tester.html${NC}"
            echo -e "${GREEN}   Meta endpoint público: $TUNNEL_URL/assets/conversions/meta-endpoint.php${NC}"
            
            # Guardar URL en archivo para referencia
            echo "$TUNNEL_URL" > .tunnel_url
        fi
        echo "$line"
    done &
    
    TUNNEL_PID=$!
else
    echo -e "\n${YELLOW}ℹ️  Para habilitar HTTPS público, instala cloudflared:${NC}"
    echo -e "${YELLOW}   brew install cloudflared${NC}"
fi

# Función para limpiar procesos al salir
cleanup() {
    echo -e "\n\n${BLUE}🛑 Cerrando servidor y servicios...${NC}"
    
    # Matar servidor PHP
    if [ ! -z "$PHP_PID" ]; then
        kill $PHP_PID 2>/dev/null
        echo -e "${GREEN}✅ Servidor PHP cerrado${NC}"
    fi
    
    # Matar tunnel si existe
    if [ "$CLOUDFLARED_AVAILABLE" = true ] && [ ! -z "$TUNNEL_PID" ]; then
        pkill cloudflared 2>/dev/null
        echo -e "${GREEN}✅ Cloudflare Tunnel cerrado${NC}"
    fi
    
    # Limpiar archivo temporal
    rm -f .tunnel_url 2>/dev/null
    
    echo -e "${BLUE}👋 ¡Desarrollo terminado!${NC}"
    exit 0
}

# Capturar señales para limpieza
trap cleanup SIGINT SIGTERM

# Abrir navegador automáticamente (opcional)
if command -v open &> /dev/null; then
    echo -e "\n${BLUE}🌐 Abriendo navegador...${NC}"
    sleep 3
    open "http://localhost:$PORT/assets/conversions/debug-tester.html" &> /dev/null &
fi

# Información de ayuda
echo -e "\n${BLUE}💡 Comandos útiles:${NC}"
echo -e "   📝 Ver logs: ${GREEN}tail -f assets/conversions/logs/debug-$(date +%Y-%m-%d).log${NC}"
echo -e "   🔄 Recargar: ${GREEN}Ctrl+C y volver a ejecutar ./start-dev.sh${NC}"
echo -e "   🛑 Cerrar: ${GREEN}Ctrl+C${NC}"

echo -e "\n${GREEN}✅ Todo listo! El servidor está corriendo...${NC}"
echo -e "${YELLOW}   Presiona Ctrl+C para cerrar${NC}"

# Mantener el script corriendo
wait