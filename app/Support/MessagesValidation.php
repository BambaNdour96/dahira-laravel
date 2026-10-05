<?php

namespace App\Support;

final class MessagesValidation
{
    /** @return array<string, string> */
    public static function tous(): array
    {
        return [
            'required' => 'Ce champ est obligatoire.',
            'string' => 'Valeur invalide.',
            'integer' => 'Valeur invalide.',
            'max' => 'Valeur trop longue.',
            'email' => 'Adresse email invalide.',
            'date' => 'Date invalide.',
            'date_naissance.after' => 'Année de naissance invalide : vérifiez les 4 chiffres de l\'année.',
            'date_naissance.regex' => 'Année de naissance invalide : vérifiez les 4 chiffres de l\'année.',
            'date_naissance.before' => 'La date de naissance doit être dans le passé.',
            'exists' => 'Choix invalide.',
            'enum' => 'Choix invalide.',
            'image' => 'Le fichier doit être une image.',
            'mimes' => 'Formats acceptés : JPEG, PNG ou WebP.',
            'photo.max' => 'La photo dépasse 5 Mo. Choisissez une image plus légère.',
            'commissions.max' => 'Vous pouvez choisir 2 commissions au maximum.',
            'commissions.*.exists' => 'Commission invalide.',
            'commissions.*.distinct' => 'Vous avez choisi deux fois la même commission.',
            'confirmed' => 'Les deux mots de passe ne correspondent pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'accepted' => 'Votre accord est nécessaire pour vous inscrire.',
            'telephone.regex' => 'Numéro invalide. Exemple : 77 123 45 67, ou +33 6 12 34 56 78 depuis l\'étranger.',
            'telephone.unique' => 'Ce numéro est déjà inscrit. Contactez votre responsable de cellule.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
        ];
    }
}
