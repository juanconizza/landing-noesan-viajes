#!/bin/bash

# Colores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔍 Monitor de Logs - Endpoints Meta y Debug${NC}"
echo -e "${YELLOW}Monitoreando logs en tiempo real...${NC}"
echo -e "${GREEN}Presiona Ctrl+C para salir${NC}\n"

# Función para mostrar logs con colores
show_logs() {
    local file=$1
    local title=$2
    local color=$3
    
    if [[ -f "$file" ]]; then
        echo -e "\n${color}=== $title ===${NC}"
        tail -n 5 "$file" | while IFS= read -r line; do
            echo -e "${color}$line${NC}"
        done
    else
        echo -e "\n${color}=== $title ===${NC}"
        echo -e "${YELLOW}Archivo de log no existe aún: $file${NC}"
    fi
}

# Función para limpiar la pantalla y mostrar logs
monitor_logs() {
    while true; do
        clear
        echo -e "${BLUE}🔍 Monitor de Logs - $(date)${NC}\n"
        
        # Debug endpoint logs
        show_logs "assets/conversions/logs/debug-$(date +%Y-%m-%d).log" "DEBUG ENDPOINT" "$GREEN"
        
        # Meta endpoint logs
        show_logs "assets/conversions/logs/meta-endpoint-$(date +%Y-%m-%d).log" "META ENDPOINT" "$BLUE"
        
        echo -e "\n${YELLOW}📊 Comandos útiles:${NC}"
        echo -e "   🧪 Test debug: ${GREEN}curl -X POST -H 'Content-Type: application/json' -d '{\"test\":\"data\"}' http://localhost:8000/assets/conversions/debug-endpoint.php${NC}"
        echo -e "   🎯 Test meta: ${GREEN}curl -X POST -H 'Content-Type: application/json' -d '{\"data\":{\"json\":{\"name\":\"Test\",\"number\":\"123\",\"eventDetails\":{\"type\":\"NewUser\",\"name\":\"Potencial Cliente\"}}}}' http://localhost:8000/assets/conversions/meta-endpoint.php${NC}"
        echo -e "\n${YELLOW}Actualizando cada 3 segundos... Ctrl+C para salir${NC}"
        
        sleep 3
    done
}

# Limpiar al salir
cleanup() {
    echo -e "\n\n${BLUE}👋 Monitor cerrado${NC}"
    exit 0
}

trap cleanup SIGINT

# Iniciar monitor
monitor_logs