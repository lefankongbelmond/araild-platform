<?php

/*
 * Correctif du 2026-11-08 : grands vides entre les paragraphes d'une publication (signalé
 * le 27/10/2026 sur la publication ARAILD). Cause : nl2br() gardait le « \n » après chaque
 * <br>, et le corps est affiché en « white-space: pre-line » → chaque saut de ligne doublé.
 */

use App\Support\SocialText;

it('n’affiche qu’une ligne vide entre deux paragraphes, même pour un texte collé', function () {
    // Texte collé depuis Word/Facebook : fins de ligne Windows, espaces insécables, lignes « vides » avec espaces.
    $collé = "🌍 ARAILD : ENSEMBLE, BÂTISSONS DES COMMUNAUTÉS !\r\n\r\n\u{00A0}\r\n\r\n"
        . "Le développement durable commence au cœur de nos communautés.\u{00A0} \r\n \r\n\r\n\r\n"
        . "Depuis sa création, ARAILD s’engage. #araild\r\n";

    $html = SocialText::render($collé);

    expect($html)->not->toContain("\n")->not->toContain("\r")
        ->and(substr_count($html, '<br>'))->toBe(4)   // 2 paragraphes séparés par une seule ligne vide chacun
        ->and($html)->toContain('COMMUNAUTÉS !<br><br>Le développement')
        ->toContain('communautés.<br><br>Depuis')
        ->toContain('class="mx-hashtag"')
        ->not->toEndWith('<br>');

    // Un saut de ligne simple reste un saut de ligne simple.
    expect(SocialText::render("ligne 1\nligne 2"))->toBe('ligne 1<br>ligne 2');
    // Le HTML saisi reste échappé.
    expect(SocialText::render("<b>gras</b>\n\n\n\nfin"))->toBe('&lt;b&gt;gras&lt;/b&gt;<br><br>fin');
});

it('nettoie le texte à l’enregistrement', function () {
    expect(SocialText::normalize("A \u{00A0}\r\n\r\n\r\n\r\nB\r\n"))->toBe("A\n\nB")
        ->and(SocialText::normalize("Un\n  \nDeux"))->toBe("Un\n\nDeux");
});
