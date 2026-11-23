<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Controlador de la pàgina d'inici i de la guia d'estils.
 */
class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('home/index', [
            'title'    => 'Inici',
            'featured' => Score::topPublished(),
            'scores'   => Score::latestPublished(3),
        ]);
    }

    /** Guia d'estils: mostra els components per revisar el disseny. No usa la BD. */
    public function styleguide(): void
    {
        $this->view('home/styleguide', ['title' => 'Guia d\'estils']);
    }
}
