<div id="Rahmen">
	<ul id="actions">
		<li><?php echo $html->link('Start', '/')?></li>
		<li><?php echo $html->link('Projects', array('controller'=>'projects', 'action' => 'index'))?>
		</li>
		<li><?php echo $html->link('About', '/pages/about/')?></li>
		<li>
		<?php
                            // wenn eingeloggt
                                if(isset($loggedInUser)) {
                                    // Username als Link anzeigen, damit er zum Account gelangt
                                    echo $html->link($loggedInUser['User']['username'], array('controller'=>'users', 'action' => 'view')) . '</b>';
									echo '<ul>';
									echo '<li>'.$html->link(__('Edit', true), array('action' => 'edit', $loggedInUser['User']['id'])).'</li>';
									echo '<li>'.$html->link('Change password', array('controller'=>'users', 'action' => 'changePassword')).'</li>';
									
									echo '<li>'.$html->link('Logout', array('controller'=>'users', 'action' => 'logout')).'</li>';
									
									echo '</ul>';
                                } else {
                                    // wenn nicht eingeloggt, dann soll man der RegistrierenLink erscheinen
									echo $html->link('Login', array('controller'=>'users', 'action' => 'login'));
									echo '<li>';
                                    echo $html->link('Register', array('controller'=>'users', 'action' => 'add'));
									echo '</li>';
                                }
        ?>
		</li>
	</ul>
</div>
<br><br>

<div class="users form">
<h2><?php  __('Account');?></h2>
    <?php $session->flash(); ?>
    
    <fieldset>
        <legend><?php __('Account bearbeiten');?></legend>
        <?php echo $form->create('User');?>
            <table>
               <tr>
                   <td><?php echo $form->label('Email'); ?></td>
                   <td><?php echo $form->input('email', array('label' => '')); ?></td>
               </tr>
            </table>
        <?php echo $form->end('Submit');?>
        
    </fieldset>
</div>


