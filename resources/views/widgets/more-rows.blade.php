{{--
    La dernière ligne d'un tableau du tableau de bord quand il en montre moins qu'il n'y en a : « + 3 autres tickets »,
    un lien vers la liste (`MySupportWidget`, `contentFooter`). `$columns` vient du tableau.
--}}
<td colspan="{{ count($columns) + 1 }}" class="fi-ta-cell" style="padding: 0.625rem 1rem;">
    <a href="{{ $url }}" class="fi-link fi-size-sm" style="font-weight: 600;">{{ $label }}</a>
</td>
