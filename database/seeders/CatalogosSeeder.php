<?php

namespace Database\Seeders;

use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Nacionalidad;
use App\Models\Pais;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use Illuminate\Database\Seeder;

/**
 * Catálogos base del sistema (brief §5.1).
 *
 * Idempotente: usa updateOrCreate, así que puede re-ejecutarse sin duplicar.
 *
 * ⚠️ El listado de municipios de Chiapas debe VALIDARSE contra el catálogo
 * oficial de INEGI antes de producción. Chiapas ha creado municipios nuevos
 * en años recientes y el conteo cambia según la fecha de corte.
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $this->paises();
        $this->estados();
        $this->municipiosDeChiapas();
        $this->clasificacionDelVisitante();
    }

    private function paises(): void
    {
        // México primero (es el caso dominante). El resto se amplía desde
        // el CRUD de catálogos conforme aparezcan.
        $paises = [
            ['México', 'MEX'], ['Estados Unidos', 'USA'], ['Canadá', 'CAN'],
            ['Guatemala', 'GTM'], ['Belice', 'BLZ'], ['El Salvador', 'SLV'],
            ['Honduras', 'HND'], ['Nicaragua', 'NIC'], ['Costa Rica', 'CRI'],
            ['Panamá', 'PAN'], ['Cuba', 'CUB'], ['República Dominicana', 'DOM'],
            ['Colombia', 'COL'], ['Venezuela', 'VEN'], ['Ecuador', 'ECU'],
            ['Perú', 'PER'], ['Bolivia', 'BOL'], ['Chile', 'CHL'],
            ['Argentina', 'ARG'], ['Uruguay', 'URY'], ['Paraguay', 'PRY'],
            ['Brasil', 'BRA'], ['España', 'ESP'], ['Francia', 'FRA'],
            ['Alemania', 'DEU'], ['Italia', 'ITA'], ['Reino Unido', 'GBR'],
            ['Países Bajos', 'NLD'], ['Bélgica', 'BEL'], ['Suiza', 'CHE'],
            ['Portugal', 'PRT'], ['Rusia', 'RUS'], ['China', 'CHN'],
            ['Japón', 'JPN'], ['Corea del Sur', 'KOR'], ['India', 'IND'],
            ['Australia', 'AUS'], ['Nueva Zelanda', 'NZL'], ['Sudáfrica', 'ZAF'],
            ['Otro', null],
        ];

        foreach ($paises as [$nombre, $iso]) {
            Pais::updateOrCreate(['nombre' => $nombre], ['iso' => $iso, 'activo' => true]);
        }
    }

    private function estados(): void
    {
        $estados = [
            'Aguascalientes', 'Baja California', 'Baja California Sur', 'Campeche',
            'Chiapas', 'Chihuahua', 'Ciudad de México', 'Coahuila', 'Colima',
            'Durango', 'Estado de México', 'Guanajuato', 'Guerrero', 'Hidalgo',
            'Jalisco', 'Michoacán', 'Morelos', 'Nayarit', 'Nuevo León', 'Oaxaca',
            'Puebla', 'Querétaro', 'Quintana Roo', 'San Luis Potosí', 'Sinaloa',
            'Sonora', 'Tabasco', 'Tamaulipas', 'Tlaxcala', 'Veracruz', 'Yucatán',
            'Zacatecas',
        ];

        foreach ($estados as $nombre) {
            Estado::updateOrCreate(['nombre' => $nombre], ['activo' => true]);
        }
    }

    private function municipiosDeChiapas(): void
    {
        $chiapas = Estado::where('nombre', 'Chiapas')->firstOrFail();

        foreach (self::MUNICIPIOS_CHIAPAS as $nombre) {
            Municipio::updateOrCreate(
                ['id_estado' => $chiapas->id, 'nombre' => $nombre],
                ['activo' => true],
            );
        }
    }

    private function clasificacionDelVisitante(): void
    {
        foreach (['NACIONAL', 'EXTRANJERO'] as $nombre) {
            Nacionalidad::updateOrCreate(['nombre' => $nombre], ['activo' => true]);
        }

        foreach (['ADULTO NACIONAL', 'ADULTO EXTRANJERO', 'NIÑO NACIONAL', 'NIÑO EXTRANJERO'] as $nombre) {
            Subnacionalidad::updateOrCreate(['nombre' => $nombre], ['activo' => true]);
        }

        foreach ([TipoAcceso::PAGO_NORMAL, TipoAcceso::GRATIS] as $nombre) {
            TipoAcceso::updateOrCreate(['nombre' => $nombre], ['activo' => true]);
        }
    }

    /**
     * ⚠️ Listado reconstruido, NO tomado de una fuente oficial. Verificar
     * contra el catálogo de INEGI antes de usarlo en producción.
     */
    private const MUNICIPIOS_CHIAPAS = [
        'Acacoyagua', 'Acala', 'Acapetahua', 'Aldama', 'Altamirano', 'Amatán',
        'Amatenango de la Frontera', 'Amatenango del Valle', 'Ángel Albino Corzo',
        'Arriaga', 'Bejucal de Ocampo', 'Belisario Domínguez', 'Bella Vista',
        'Benemérito de las Américas', 'Berriozábal', 'Bochil', 'Cacahoatán',
        'Capitán Luis Ángel Vidal', 'Catazajá', 'Chalchihuitán', 'Chamula',
        'Chanal', 'Chapultenango', 'Chenalhó', 'Chiapa de Corzo', 'Chiapilla',
        'Chicoasén', 'Chicomuselo', 'Chilón', 'Cintalapa', 'Coapilla',
        'Comitán de Domínguez', 'Copainalá', 'El Bosque', 'El Parral',
        'El Porvenir', 'Emiliano Zapata', 'Escuintla', 'Francisco León',
        'Frontera Comalapa', 'Frontera Hidalgo', 'Huehuetán', 'Huitiupán',
        'Huixtán', 'Huixtla', 'Ixhuatán', 'Ixtacomitán', 'Ixtapa',
        'Ixtapangajoya', 'Jiquipilas', 'Jitotol', 'Juárez', 'La Concordia',
        'La Grandeza', 'La Independencia', 'La Libertad', 'La Trinitaria',
        'Larráinzar', 'Las Margaritas', 'Las Rosas', 'Mapastepec',
        'Maravilla Tenejapa', 'Marqués de Comillas', 'Mazapa de Madero',
        'Mazatán', 'Metapa', 'Mezcalapa', 'Mitontic', 'Montecristo de Guerrero',
        'Motozintla', 'Nicolás Ruíz', 'Ocosingo', 'Ocotepec',
        'Ocozocoautla de Espinosa', 'Ostuacán', 'Osumacinta', 'Oxchuc',
        'Palenque', 'Pantelhó', 'Pantepec', 'Pichucalco', 'Pijijiapan',
        'Pueblo Nuevo Solistahuacán', 'Rayón', 'Reforma',
        'Rincón Chamula San Pedro', 'Sabanilla', 'Salto de Agua',
        'San Andrés Duraznal', 'San Cristóbal de las Casas', 'San Fernando',
        'San Juan Cancuc', 'San Lucas', 'Santiago el Pinar', 'Siltepec',
        'Simojovel', 'Sitalá', 'Socoltenango', 'Solosuchiapa', 'Soyaló',
        'Suchiapa', 'Suchiate', 'Sunuapa', 'Tapachula', 'Tapalapa', 'Tapilula',
        'Tecpatán', 'Tenejapa', 'Teopisca', 'Tila', 'Tonalá', 'Totolapa',
        'Tumbalá', 'Tuxtla Chico', 'Tuxtla Gutiérrez', 'Tuzantán', 'Tzimol',
        'Unión Juárez', 'Venustiano Carranza', 'Villa Comaltitlán',
        'Villa Corzo', 'Villaflores', 'Yajalón', 'Zinacantán',
    ];
}
