<?php

namespace App\Automations;

use App\Models\Automation;
use App\Models\Board;
use Illuminate\Support\Str;

/**
 * Renders a rule as one natural-language sentence for the builder listing and
 * the wizard review — "when a card is added to list X by me, move the card to
 * the top of list Y, then archive the card".
 */
class SentenceRenderer
{
    public function render(Automation $automation): string
    {
        $board = $automation->board;
        $config = $automation->trigger_config ?? [];

        $parts = [];
        $trigger = $this->triggerSentence($automation->trigger_type, $config, $board);

        if ($automation->actor_scope === Automation::ACTOR_ME) {
            $trigger .= ' '.__('par moi');
        }

        $parts[] = $trigger;

        $conditions = collect($automation->conditionList())
            ->map(fn (array $c) => $this->conditionSentence($c['type'], $c['config'], $board))
            ->filter();

        if ($conditions->isNotEmpty()) {
            $parts[] = __('si').' '.$conditions->implode(' '.__('et').' ');
        }

        $actions = collect($automation->actionList())
            ->map(fn (array $a) => $this->actionSentence($a['type'], $a['config'], $board))
            ->filter();

        $parts[] = $actions->isNotEmpty() ? $actions->implode(', '.__('puis').' ') : __('(aucune action)');

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function triggerSentence(string $type, array $config, Board $board): string
    {
        return match ($type) {
            'card.created' => empty($config['list_id'])
                ? __('quand une carte est ajoutée au tableau')
                : __('quand une carte est ajoutée à la liste ":list"', ['list' => $this->listName($board, $config['list_id'])]),
            'card.moved_to_list' => __('quand une carte entre dans la liste ":list"', ['list' => $this->listName($board, $config['list_id'] ?? 0)]),
            'card.moved_from_list' => __('quand une carte quitte la liste ":list"', ['list' => $this->listName($board, $config['list_id'] ?? 0)]),
            'card.archived' => __('quand une carte est archivée'),
            'list.has_n_cards' => __('quand la liste ":list" a :op :count cartes', [
                'list' => $this->listName($board, $config['list_id'] ?? 0),
                'op' => ($config['op'] ?? 'at_least') === 'exactly' ? __('exactement') : __('au moins'),
                'count' => (int) ($config['count'] ?? 0),
            ]),
            'card.completed' => __('quand une carte est marquée terminée'),
            'card.label_added' => empty($config['label_id'])
                ? __('quand un label est ajouté')
                : __('quand le label ":label" est ajouté', ['label' => $this->labelName($board, $config['label_id'])]),
            'card.label_removed' => empty($config['label_id'])
                ? __('quand un label est retiré')
                : __('quand le label ":label" est retiré', ['label' => $this->labelName($board, $config['label_id'])]),
            'card.member_assigned' => empty($config['user_id'])
                ? __('quand un membre est assigné')
                : __('quand ":member" est assigné', ['member' => $this->memberName($board, $config['user_id'])]),
            'card.due_set' => __('quand une échéance est définie'),
            'card.due_soon' => __("quand l'échéance d'une carte approche"),
            'card.due_relative' => trans_choice(
                ($config['direction'] ?? 'before') === 'after'
                    ? '{1}:count jour après l’échéance|[2,*]:count jours après l’échéance'
                    : '{1}:count jour avant l’échéance|[2,*]:count jours avant l’échéance',
                max(1, (int) ($config['days'] ?? 1)),
            ),
            'scheduled' => $this->scheduleSentence($config),
            'checklist.added' => __('quand une checklist est ajoutée'),
            'checklist.item_checked' => __('quand un élément de checklist est coché'),
            'checklist.completed' => __('quand une checklist est entièrement cochée'),
            'comment.added' => filled($config['text'] ?? '')
                ? __('quand un commentaire contenant ":text" est ajouté', ['text' => $config['text']])
                : __('quand un commentaire est ajouté'),
            'card.title_contains' => __('quand le titre d’une carte contient ":text"', ['text' => $config['text'] ?? '']),
            'custom_field.changed' => filled($config['value'] ?? '')
                ? __('quand le champ ":field" prend la valeur ":value"', ['field' => $this->fieldName($board, $config['field_id'] ?? 0), 'value' => $config['value']])
                : __('quand le champ ":field" change', ['field' => $this->fieldName($board, $config['field_id'] ?? 0)]),
            'manual' => __('au clic sur le bouton'),
            'board_button' => __('au clic sur le bouton du tableau'),
            default => $type,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function scheduleSentence(array $config): string
    {
        $at = ' '.__('à :time', ['time' => $config['at'] ?? '09:00']);
        $days = collect((array) ($config['days'] ?? []))->map(fn ($d) => $this->dayName((string) $d))->implode(', ');

        return match ($config['freq'] ?? '') {
            'daily' => __('tous les jours').$at,
            'days' => __('chaque :days', ['days' => $days ?: '—']).$at,
            'every_n_weeks' => __('toutes les :n semaines le :days', ['n' => max(1, (int) ($config['n'] ?? 1)), 'days' => $days ?: '—']).$at,
            'monthly_first_dow' => __('le premier :dow du mois', ['dow' => $this->dayName((string) ($config['dow'] ?? 'monday'))]).$at,
            'monthly_day' => __('chaque mois le :day', ['day' => (int) ($config['day'] ?? 1)]).$at,
            'yearly' => __('chaque année le :day/:month', ['day' => (int) ($config['day'] ?? 1), 'month' => (int) ($config['month'] ?? 1)]).$at,
            default => __('programmée'),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function actionSentence(string $type, array $config, Board $board): string
    {
        return match ($type) {
            'move_to_list' => ($config['position'] ?? 'bottom') === 'top'
                ? __('déplacer la carte en haut de la liste ":list"', ['list' => $this->listName($board, $config['list_id'] ?? 0)])
                : __('déplacer la carte vers la liste ":list"', ['list' => $this->listName($board, $config['list_id'] ?? 0)]),
            'move_in_list' => ($config['position'] ?? 'top') === 'top'
                ? __('déplacer la carte en haut de sa liste')
                : __('déplacer la carte en bas de sa liste'),
            'sort_list' => __('trier la liste :list par :by', [
                'list' => empty($config['list_id']) ? __('courante') : '"'.$this->listName($board, $config['list_id']).'"',
                'by' => match ($config['by'] ?? 'due') {
                    'title' => __('titre'),
                    'created' => __('date de création'),
                    default => __('échéance'),
                },
            ]),
            'archive_card' => __('archiver la carte'),
            'archive_list_cards' => __('archiver toutes les cartes de la liste :list', [
                'list' => empty($config['list_id']) ? __('courante') : '"'.$this->listName($board, $config['list_id']).'"',
            ]),
            'assign_label' => __('ajouter le label ":label"', ['label' => $this->labelName($board, $config['label_id'] ?? 0)]),
            'remove_label' => __('retirer le label ":label"', ['label' => $this->labelName($board, $config['label_id'] ?? 0)]),
            'assign_member' => __('assigner :member', ['member' => $this->memberOrMe($board, $config['user_id'] ?? null)]),
            'unassign_member' => __('désassigner :member', ['member' => $this->memberOrMe($board, $config['user_id'] ?? null)]),
            'add_checklist' => __('ajouter la checklist ":title"', ['title' => $config['title'] ?? 'Checklist']),
            'create_card' => __('créer une carte ":title" dans la liste :list:unique', [
                'title' => Str::limit((string) ($config['title'] ?? ''), 40),
                'list' => empty($config['list_id']) ? __('courante') : '"'.$this->listName($board, $config['list_id']).'"',
                'unique' => empty($config['unique']) ? '' : ' '.__('(unique)'),
            ]),
            'copy_card' => __('copier la carte vers la liste ":list"', ['list' => $this->listName($board, $config['list_id'] ?? 0)]),
            'create_follow_up_card' => __('créer une carte de suivi liée'),
            'set_due_date' => __("définir l'échéance à +:days jours", ['days' => (int) ($config['days'] ?? 0)]),
            'clear_due_date' => __("retirer l'échéance"),
            'mark_complete' => __('marquer la carte terminée'),
            'mark_incomplete' => __('marquer la carte non terminée'),
            'post_comment' => __('publier le commentaire ":body"', ['body' => Str::limit((string) ($config['body'] ?? ''), 40)]),
            'set_custom_field' => __('définir le champ ":field" à ":value"', [
                'field' => $this->fieldName($board, $config['field_id'] ?? 0),
                'value' => $config['value'] ?? '',
            ]),
            'notify_members' => __('notifier les membres de la carte'),
            'send_webhook' => __('envoyer un webhook'),
            // Plugin-contributed actions render their declared label.
            default => app(AutomationRegistry::class)->action($type)?->label() ?? $type,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function conditionSentence(string $type, array $config, Board $board): string
    {
        return match ($type) {
            'has_label' => __('la carte a le label ":label"', ['label' => $this->labelName($board, $config['label_id'] ?? 0)]),
            'in_list' => __('la carte est dans la liste ":list"', ['list' => $this->listName($board, $config['list_id'] ?? 0)]),
            'assigned_to' => __('":member" est assigné', ['member' => $this->memberName($board, $config['user_id'] ?? 0)]),
            'custom_field_equals' => __('le champ ":field" vaut ":value"', ['field' => $this->fieldName($board, $config['field_id'] ?? 0), 'value' => $config['value'] ?? '']),
            'title_contains' => __('le titre contient ":text"', ['text' => $config['text'] ?? '']),
            'has_due_date' => __('la carte a une échéance'),
            default => $type,
        };
    }

    private function listName(Board $board, mixed $id): string
    {
        return $board->lists()->whereKey((int) $id)->value('name') ?? '?';
    }

    private function labelName(Board $board, mixed $id): string
    {
        $label = $board->labels()->whereKey((int) $id)->first();

        return $label?->name ?? ($label ? __('label sans nom') : '?');
    }

    private function memberName(Board $board, mixed $id): string
    {
        return $board->members()->whereKey((int) $id)->value('name') ?? '?';
    }

    private function memberOrMe(Board $board, mixed $id): string
    {
        return $id === 'me' ? __('l’utilisateur qui déclenche') : '"'.$this->memberName($board, $id).'"';
    }

    private function fieldName(Board $board, mixed $id): string
    {
        return $board->customFields()->whereKey((int) $id)->value('name') ?? '?';
    }

    private function dayName(string $day): string
    {
        return match (strtolower($day)) {
            'monday' => __('lundi'),
            'tuesday' => __('mardi'),
            'wednesday' => __('mercredi'),
            'thursday' => __('jeudi'),
            'friday' => __('vendredi'),
            'saturday' => __('samedi'),
            'sunday' => __('dimanche'),
            default => $day,
        };
    }
}
