# Ecosistema Financiero de Unicornio Software - WebView APK

APK Android (WebView) que carga el ecosistema local empaquetado y apunta los servicios remotos a `https://unicorniosoftware.gt.tc/`.

## Compilar sin Android Studio (GitHub Actions)

1. Haz commit y push de los cambios al repo.
2. En GitHub, ve a **Actions** > workflow **Build Android APK**.
3. Ejecútalo con **Run workflow** (o espera el trigger por push).
4. Al terminar, descarga el artifact **EcosisUnicorn-apk**.
5. Dentro viene `EcosisUnicorn.apk` para instalar en Android (habilita "Instalar apps desconocidas" si hace falta).

El workflow también actualiza automáticamente `android-apk/dist/EcosisUnicorn.apk` en el repo (commit automático con `[skip ci]`), para que al hacer `git pull` tengas la última APK en tu carpeta local.

## Compilar por línea de comandos (PC)

Requisitos: JDK 17, Android SDK (platform 35, build-tools 35.0.0), Gradle 8.7.

```bash
cd android-apk
gradle assembleDebug
```

APK generada por Gradle: `app/build/outputs/apk/debug/app-debug.apk`  
Copia local recomendada: `dist/EcosisUnicorn.apk`

## Notas

- La app carga la URL de producción en InfinityFree; requiere internet.
- Si InfinityFree muestra un reto/anti-bot JS, puede aparecer en la primera carga; normalmente el WebView lo resuelve.
- Para release/Play Store se necesita firmar la APK/AAB con keystore.
