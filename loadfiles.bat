:: LIMPIA TABLA TMP_APPLEMUSIC
sqlcmd -S fonarte2.database.windows.net -d Reporteador -U Dataguys2 -P Fonarte2018 -I -Q "TRUNCATE TABLE TMP_APPLEMUSIC;"

:: LIMPIA TABLA TMP_ITUNES
sqlcmd -S fonarte2.database.windows.net -d Reporteador -U Dataguys2 -P Fonarte2018 -I -Q "TRUNCATE TABLE TMP_ITUNES;"

:: LIMPIA TABLA TMP_FINANCIAL_REPORT
sqlcmd -S fonarte2.database.windows.net -d Reporteador -U Dataguys2 -P Fonarte2018 -I -Q "TRUNCATE TABLE TMP_FINANCIAL_REPORT;"

:: LIMPIA TABLA TMP_ORCHARD
sqlcmd -S fonarte2.database.windows.net -d Reporteador -U Dataguys2 -P Fonarte2018 -I -Q "TRUNCATE TABLE TMP_ORCHARD;"

:: RENOMBRA ORCHARD
REN C:\xampp\htdocs\SubidaBi\REPORTES_FONARTE\*fullreport*.txt TMP_ORCHARD.txt;

:: RENOMBRA APPLEMUSIC
REN C:\xampp\htdocs\SubidaBi\REPORTES_FONARTE\S1*.txt TMP_APPLEMUSIC.txt;

:: RENOMBRA ITUNES
REN C:\xampp\htdocs\SubidaBi\REPORTES_FONARTE\*_ZZ*.txt TMP_ITUNES.txt;

:: RENOMBRA FINANCIAL_REPORT
REN C:\xampp\htdocs\SubidaBi\REPORTES_FONARTE\financial*.txt TMP_FINANCIAL_REPORT.txt;

:: CARGA REPORTE APPLEMUSIC
bcp TMP_APPLEMUSIC in C:\xampp\htdocs\SubidaBi\REPORTES_FONARTE\TMP_APPLEMUSIC.txt -S fonarte2.database.windows.net -d Reporteador -U Dataguys2 -P Fonarte2018 -q -c -t \t -r 0x0A -C 65001;

:: CARGA REPORTE ITUNES
bcp TMP_ITUNES in C:\xampp\htdocs\SubidaBi\REPORTES_FONARTE\TMP_ITUNES.txt -S fonarte2.database.windows.net -d Reporteador -U Dataguys2 -P Fonarte2018 -q -c -t \t -r 0x0A -C 65001;

:: CARGA REPORTE DIVISAS
bcp TMP_FINANCIAL_REPORT in C:\xampp\htdocs\SubidaBi\REPORTES_FONARTE\TMP_FINANCIAL_REPORT.txt -S fonarte2.database.windows.net -d Reporteador -U Dataguys2 -P Fonarte2018 -q -c -t \t -r 0x0A -C 65001;

:: CARGA REPORTE ORCHARD
bcp TMP_ORCHARD in C:\xampp\htdocs\SubidaBi\REPORTES_FONARTE\TMP_ORCHARD.txt -S fonarte2.database.windows.net -d Reporteador -U Dataguys2 -P Fonarte2018 -q -c -t \t -r 0x0A -C 65001;

:: ACTUALIZA TABLA APPLEMUSIC
sqlcmd -S fonarte2.database.windows.net -d Reporteador -U Dataguys2 -P Fonarte2018 -I -Q "INSERT INTO APPLEMUSIC SELECT * FROM TMP_APPLEMUSIC;"

:: ACTUALIZA TABLA ITUNES
sqlcmd -S fonarte2.database.windows.net -d Reporteador -U Dataguys2 -P Fonarte2018 -I -Q "INSERT INTO ITUNES SELECT * FROM TMP_ITUNES;"

:: ACTUALIZA TABLA ORCHARD
sqlcmd -S fonarte2.database.windows.net -d Reporteador -U Dataguys2 -P Fonarte2018 -I -Q "INSERT INTO ORCHARD SELECT * FROM TMP_ORCHARD;"

:: ACTUALIZA TABLA APPLE_CURRENCY
sqlcmd -S fonarte2.database.windows.net -d Reporteador -U Dataguys2 -P Fonarte2018 -I -Q "INSERT INTO APPLE_CURRENCY SELECT LEFT(RIGHT(DIVISA,4),3), TIPO_DE_CAMBIO, ANIO, MES FROM TMP_FINANCIAL_REPORT;"

:: MUEVE REPORTES PROCESADOS
Robocopy C:\xampp\htdocs\SubidaBi\REPORTES_FONARTE C:\xampp\htdocs\SubidaBi\REPORTES_FONARTE_PROCESADOS /mov /s /w:1 /r:1