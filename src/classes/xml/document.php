<?php
/**
 * PHP VCS wrapper Xml document base
 *
 * This file is part of vcs-wrapper.
 *
 * vcs-wrapper is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Lesser General Public License as published by the Free
 * Software Foundation; version 3 of the License.
 *
 * vcs-wrapper is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Lesser General Public License for
 * more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with vcs-wrapper; if not, write to the Free Software Foundation, Inc., 51
 * Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @package VCSWrapper
 * @subpackage Xml
 * @version $Revision: 14 $
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt LGPLv3
 */

/*
 * XML reader
 *
 * Custom XML reader using the PHP xmlreader, exposing an interface similar to
 * simple XML, but implementing vcsCacheable, so the parsed XML structure can
 * be cached.
 */
class vcsXml extends vcsXmlNode implements vcsCacheable
{
    /**
     * Create XML document from file
     * 
     * @param string $xmlFile
     * @return vcsXml
     */
    public static function loadFile( $xmlFile )
    {
        // Check if user exists at all
        if ( !is_file( $xmlFile ) ||
             !is_readable( $xmlFile ) )
        {
            throw new vcsNoSuchFileException( $xmlFile );
        }

        return self::parseXml( $xmlFile );
    }

    /**
     * Create XML document from string
     * 
     * @param string $xmlString
     * @return vcsXml
     */
    public static function loadString( $xmlString )
    {
        $xmlFile = tempnam( sys_get_temp_dir(), 'xml_' );
        file_put_contents( $xmlFile, $xmlString );
        $xml = self::parseXml( $xmlFile );
        unlink( $xmlFile );
        return $xml;
    }

    /**
     * Parse XML file
     *
     * Parse the given XML into vcsXmlNode objects using the XMLReader class.
     * 
     * @param string $xmlFile 
     * @return vcsXmlNode
     */
    protected static function parseXml( $xmlFile )
    {
        $reader = new XMLReader();

        // Use custom error handling to suppress warnings and errors during
        // parsing.
        $libXmlErrors = libxml_use_internal_errors( true );

        // Try to open configuration file, and throw parsing exception if
        // something fails.
        $errors = array();

        // Just open, errors will not occure before actually reading.
        $reader->open( $xmlFile );

        // Current node, processed. Start with a reference to th root node.
        $current = $root = new static();

        // Stack of parents for the current node. We store this list, because
        // we do not want to store a parent node reference in the nodes, as
        // this breaks with var_export'ing those structures.
        $parents = array( $root );

        // Start processing the XML document
        //
        // The read method may issue warning, even if
        // libxml_use_internal_errors was set to true. That sucks, and we need
        // to use the @ here...
        while( @$reader->read() )
        {
            switch( $reader->nodeType )
            {
                case XMLReader::ELEMENT:
                    // A new element, which results in a new configuration node as
                    // a child of the current node
                    //
                    // Get name of new element
                    $nodeName = $reader->name;

                    // We create a new object, so append the current node as
                    // future parent node to the parent stack.
                    array_push( $parents, $current );

                    // Create new child and reference node as current working
                    // node
                    $current = $current->$nodeName = new vcsXmlNode();

                    // After reading the elements we need to know about this
                    // for further progressing
                    $emptyElement = $reader->isEmptyElement;

                    // Process elements attributes, if available
                    if ( $reader->hasAttributes )
                    {
                        // Read all attributes and store their values in the
                        // current configuration node
                        while( $reader->moveToNextAttribute() )
                        {
                            $current[$reader->name] = $reader->value;
                        }
                    }

                    if ( !$emptyElement )
                    {
                        // We only break for non empty elements.
                        //
                        // For empty elements the element may also be counted
                        // as a closing tag, so that we want also process the
                        // next case statement.
                        break;
                    }

                case XMLReader::END_ELEMENT:
                    // At the end of a element set the current pointer back to its
                    // parent
                    //
                    // Pop new current node from parents stack
                    $current = array_pop( $parents );
                    break;

                case XMLReader::TEXT:
                case XMLReader::CDATA:
                    // Text and CData node are added as node content.
                    //
                    // Append string, in case several text or Cdata nodes exist
                    // in one node
                    $current->setContent( (string) $current . $reader->value );
                    break;

                // Everything else can be ignored for now..
            }
        }

        // Check if errors occured while reading configuration
        if ( count( $errors = libxml_get_errors() ) )
        {
            // Reset libxml error handling to old state
            libxml_use_internal_errors( $libXmlErrors );
            libxml_clear_errors();

            throw new vcsXmlParserException( $xmlFile, $errors );
        }

        // Reset libxml error handling to old state
        libxml_use_internal_errors( $libXmlErrors );
        return $root->skipRoot();
    }

    /**
     * Skip root node
     * 
     * SimpleXML offers direct access to the childs of the root, without any
     * information about the actual root node. We do the same by just skipping
     * from the root node its first child.
     * 
     * @return void
     */
    protected function skipRoot()
    {
        $rootList = reset( $this->childs );
        return $rootList[0]->toDocument();
    }

    /**
     * Set object state after var_export.
     * 
     * Set object state after var_export.
     * 
     * @param array $array 
     * @param string $class
     * @return vcsXml
     */
    public static function __set_state( array $array, $class = 'vcsXmlNode' )
    {
        return parent::__set_state( $array, __CLASS__ );
    }
}
