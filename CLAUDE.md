# Backend API – Sistema Web de Gestión Logística e Inventario de Productos Electrónicos

Repositorio `inventario-electronico-backend` del **Grupo 6 – Ingeniería de Software 2026-II (UNA Puno)**.
Lee y respeta obligatoriamente `AGENTS.md` y todas las reglas definidas en `.agents/rules/`:
- `.agents/rules/aislamiento-equipo-backend.md` (Fronteras de archivos entre William y Anthony).
- `.agents/rules/contrato-endpoints.md` (Contrato estricto de Endpoints con el Frontend de Carlos y Franklin).
- `.agents/rules/arquitectura-y-concurrencia.md` (Transacciones ACID con `lockForUpdate()`, Sanctum, FormRequests y JsonResources).

Todos los comandos de PHP, Composer y Artisan deben ejecutarse mediante Docker desde `../infra`:
```powershell
docker compose -f ../infra/compose.yaml exec backend php artisan <comando>
```
